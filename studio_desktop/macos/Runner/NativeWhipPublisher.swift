import Foundation
import WebRTC

/// WHIP publisher — Opus offer to MediaMTX, mix injected via capture post-processing.
final class NativeWhipPublisher: NSObject {
  enum State: String {
    case idle, connecting, connected, failed
  }

  private var factory: RTCPeerConnectionFactory?
  private var pc: RTCPeerConnection?
  private var audioTrack: RTCAudioTrack?
  private var resourceURL: URL?
  private let mixInjector = MixCaptureInjector()

  private(set) var state: State = .idle
  private(set) var iceState: String = "new"

  var onState: ((State) -> Void)?
  var onIce: ((String) -> Void)?

  private weak var audioEngine: NativeAudioEngine?

  func attach(engine: NativeAudioEngine) {
    audioEngine = engine
    mixInjector.engine = engine
  }

  func goLive(whipUrl: String, completion: @escaping (Error?) -> Void) {
    // Always complete on main — Flutter MethodChannel + Timers require it.
    let finish: (Error?) -> Void = { error in
      DispatchQueue.main.async {
        completion(error)
      }
    }

    stop()
    setState(.connecting)

    // Drop any pre-roll mix so the first live packets use current fader/mute.
    audioEngine?.clearMixRing()

    // Never bypass APM — MixCaptureInjector runs in capture post-processing.
    // (bypassVoiceProcessing:true skipped the injector → silent / wrong mic on air.)
    let factory = SMMakePeerConnectionFactory(false, mixInjector)
    self.factory = factory

    // Point WebRTC's mic at the same hardware Studio armed — otherwise Listen
    // stays silent when the system default is AirBeam / a virtual device.
    bindAdmInput(factory: factory)

    let config = RTCConfiguration()
    config.sdpSemantics = .unifiedPlan
    config.iceServers = [RTCIceServer(urlStrings: ["stun:stun.l.google.com:19302"])]
    config.continualGatheringPolicy = .gatherOnce
    let constraints = RTCMediaConstraints(
      mandatoryConstraints: nil,
      optionalConstraints: ["DtlsSrtpKeyAgreement": "true"]
    )
    guard let pc = factory.peerConnection(
      with: config,
      constraints: constraints,
      delegate: self
    ) else {
      setState(.failed)
      finish(
        NSError(
          domain: "WHIP",
          code: 1,
          userInfo: [NSLocalizedDescriptionKey: "Failed to create peer connection"]
        )
      )
      return
    }
    self.pc = pc

    let track = factory.audioTrack(withTrackId: "audio0")
    audioTrack = track

    // Match web Studio: high-bitrate stereo Opus from the first offer.
    let encoding = RTCRtpEncodingParameters()
    encoding.isActive = true
    encoding.maxBitrateBps = NSNumber(value: 510_000)
    encoding.bitratePriority = 4.0
    encoding.networkPriority = .high

    let init_ = RTCRtpTransceiverInit()
    init_.direction = .sendOnly
    init_.streamIds = ["soundmix"]
    init_.sendEncodings = [encoding]

    let transceiver = pc.addTransceiver(with: track, init: init_)
    preferOpusCodec(on: transceiver, factory: factory)

    let offerConstraints = RTCMediaConstraints(
      mandatoryConstraints: [
        "OfferToReceiveAudio": "false",
        "OfferToReceiveVideo": "false",
      ],
      optionalConstraints: nil
    )

    pc.offer(for: offerConstraints) { [weak self] (sdp: RTCSessionDescription?, error: Error?) in
      DispatchQueue.main.async {
        guard let self else { return }
        if let error {
          self.setState(.failed)
          finish(error)
          return
        }
        guard let sdp, !sdp.sdp.isEmpty else {
          self.setState(.failed)
          finish(
            NSError(
              domain: "WHIP",
              code: 2,
              userInfo: [NSLocalizedDescriptionKey: "Empty offer from WebRTC"]
            )
          )
          return
        }
        self.setLocalOffer(sdp, on: pc) { error in
          if let error {
            self.setState(.failed)
            finish(error)
            return
          }
          self.applyMaxAudioBitrate(on: pc)
          self.waitForIceThenPublish(pc: pc, whipUrl: whipUrl, completion: finish)
        }
      }
    }
  }

  /// Prefer Opus over telephone codecs (matches web `forceOpusCodec`).
  private func preferOpusCodec(on transceiver: RTCRtpTransceiver?, factory: RTCPeerConnectionFactory) {
    guard let transceiver else { return }
    let caps = factory.rtpSenderCapabilities(forKind: kRTCMediaStreamTrackKindAudio)
    let opus = caps.codecs.filter { $0.name.lowercased() == "opus" }
    guard !opus.isEmpty else { return }
    // Property setter — method form is not imported cleanly into Swift.
    transceiver.codecPreferences = opus
  }

  /// Apply high-quality Opus SDP; fall back to the original offer if WebRTC rejects it.
  private func setLocalOffer(
    _ offer: RTCSessionDescription,
    on pc: RTCPeerConnection,
    completion: @escaping (Error?) -> Void
  ) {
    let tunedSdp = Self.preferHighQualityOpus(offer.sdp)
    let tuned = RTCSessionDescription(type: offer.type, sdp: tunedSdp)
    pc.setLocalDescription(tuned) { error in
      DispatchQueue.main.async {
        if error == nil, pc.localDescription != nil {
          completion(nil)
          return
        }
        // Older failures came from bad fmtp rewrites — keep publishing either way.
        pc.setLocalDescription(offer) { fallbackError in
          DispatchQueue.main.async {
            completion(fallbackError)
          }
        }
      }
    }
  }

  private func applyMaxAudioBitrate(on pc: RTCPeerConnection) {
    for sender in pc.senders where sender.track?.kind == kRTCMediaStreamTrackKindAudio {
      let params = sender.parameters
      var encodings = params.encodings
      if encodings.isEmpty {
        let encoding = RTCRtpEncodingParameters()
        encoding.isActive = true
        encodings = [encoding]
      }
      for encoding in encodings {
        encoding.maxBitrateBps = NSNumber(value: 510_000)
        encoding.bitratePriority = 4.0
        encoding.networkPriority = .high
      }
      params.encodings = encodings
      sender.parameters = params
    }
  }

  private func bindAdmInput(factory: RTCPeerConnectionFactory) {
    let adm = factory.audioDeviceModule
    let wanted = audioEngine?.selectedInputLabel?.lowercased() ?? ""
    let devices = adm.inputDevices
    let match =
      devices.first(where: {
        let n = $0.name.lowercased()
        guard !wanted.isEmpty, !n.isEmpty else { return false }
        return n == wanted || n.contains(wanted) || wanted.contains(n)
      })
      ?? devices.first(where: {
        let n = $0.name.lowercased()
        return n.contains("macbook") || n.contains("built-in")
      })
      ?? devices.first(where: { $0.isDefault })
    if let match {
      _ = adm.trySetInputDevice(match)
    }
    _ = adm.initRecording()
    _ = adm.startRecording()
  }

  /// Release WebRTC's mic briefly so AVAudioEngine can rebind CoreAudio without contention.
  func pauseCaptureForDeviceSwap() {
    guard state == .connected || state == .connecting, let factory else { return }
    mixInjector.beginHold()
    let adm = factory.audioDeviceModule
    _ = adm.stopRecording()
  }

  /// After Studio switches hardware, point ADM at the new mic and resume capture.
  func rebindAdmAfterDeviceSwap() {
    guard state == .connected || state == .connecting, let factory else {
      mixInjector.endHold()
      return
    }
    bindAdmInput(factory: factory)
    // Keep hold until the native ring is feeding again (engine clears hot-swap flag).
    DispatchQueue.main.asyncAfter(deadline: .now() + 0.15) { [weak self] in
      self?.mixInjector.endHold()
    }
  }

  func stop() {
    if let resourceURL {
      var req = URLRequest(url: resourceURL)
      req.httpMethod = "DELETE"
      URLSession.shared.dataTask(with: req).resume()
    }
    resourceURL = nil
    audioTrack = nil
    pc?.close()
    pc = nil
    factory = nil
    iceState = "closed"
    onIce?(iceState)
    setState(.idle)
  }

  private func waitForIceThenPublish(
    pc: RTCPeerConnection,
    whipUrl: String,
    completion: @escaping (Error?) -> Void
  ) {
    // Must run on main — Timer.scheduledTimer on WebRTC threads never fires (UI stuck on Going live…).
    dispatchPrecondition(condition: .onQueue(.main))
    if pc.iceGatheringState == .complete {
      postOffer(whipUrl: whipUrl, completion: completion)
      return
    }
    var attempts = 0
    Timer.scheduledTimer(withTimeInterval: 0.05, repeats: true) { [weak self] timer in
      attempts += 1
      // ~1.5s max gather, then publish whatever candidates we have.
      if pc.iceGatheringState == .complete || attempts >= 30 {
        timer.invalidate()
        self?.postOffer(whipUrl: whipUrl, completion: completion)
      }
    }
  }

  private func postOffer(whipUrl: String, completion: @escaping (Error?) -> Void) {
    guard let pc else {
      setState(.failed)
      completion(
        NSError(
          domain: "WHIP",
          code: 3,
          userInfo: [NSLocalizedDescriptionKey: "Peer connection missing"]
        )
      )
      return
    }
    guard let local = pc.localDescription, !local.sdp.isEmpty else {
      setState(.failed)
      completion(
        NSError(
          domain: "WHIP",
          code: 3,
          userInfo: [NSLocalizedDescriptionKey: "No local session description yet"]
        )
      )
      return
    }
    guard let url = URL(string: whipUrl) else {
      setState(.failed)
      completion(
        NSError(
          domain: "WHIP",
          code: 4,
          userInfo: [NSLocalizedDescriptionKey: "Bad WHIP URL"]
        )
      )
      return
    }
    var req = URLRequest(url: url)
    req.httpMethod = "POST"
    req.setValue("application/sdp", forHTTPHeaderField: "Content-Type")
    req.setValue("application/sdp", forHTTPHeaderField: "Accept")
    req.timeoutInterval = 15
    req.httpBody = local.sdp.data(using: .utf8)

    URLSession.shared.dataTask(with: req) { [weak self] data, response, error in
      DispatchQueue.main.async {
        guard let self else { return }
        if let error {
          self.setState(.failed)
          completion(error)
          return
        }
        guard let http = response as? HTTPURLResponse,
              (200..<300).contains(http.statusCode),
              let data,
              let answer = String(data: data, encoding: .utf8),
              !answer.isEmpty
        else {
          let code = (response as? HTTPURLResponse)?.statusCode ?? 0
          self.setState(.failed)
          completion(
            NSError(
              domain: "WHIP",
              code: 5,
              userInfo: [
                NSLocalizedDescriptionKey:
                  "WHIP publish failed (HTTP \(code)). Check stream is online.",
              ]
            )
          )
          return
        }
        if let location = http.value(forHTTPHeaderField: "Location"), !location.isEmpty {
          self.resourceURL = URL(string: location, relativeTo: url)?.absoluteURL
        }
        let remote = RTCSessionDescription(type: .answer, sdp: answer)
        self.pc?.setRemoteDescription(remote) { error in
          DispatchQueue.main.async {
            if let error {
              self.setState(.failed)
              completion(error)
              return
            }
            if let pc = self.pc {
              self.applyMaxAudioBitrate(on: pc)
            }
            self.setState(.connected)
            completion(nil)
          }
        }
      }
    }.resume()
  }

  private func setState(_ next: State) {
    state = next
    onState?(next)
  }

  /// Same Opus fmtp as web Studio (`preferHighQualityOpus` in studio.js).
  static func preferHighQualityOpus(_ sdp: String) -> String {
    let bitrate = 510_000
    let fmtpValue =
      "minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=\(bitrate);maxplaybackrate=48000"

    var normalized = sdp.replacingOccurrences(of: "\r\n", with: "\n")
    normalized = normalized.replacingOccurrences(of: "\r", with: "\n")
    while normalized.hasSuffix("\n") {
      normalized.removeLast()
    }
    var lines = normalized.components(separatedBy: "\n")

    var opusPts = Set<String>()
    for line in lines {
      let lower = line.lowercased()
      guard lower.hasPrefix("a=rtpmap:"), lower.contains("opus/48000") else { continue }
      let rest = line.dropFirst("a=rtpmap:".count)
      let pt = String(rest.prefix(while: { $0.isNumber }))
      if !pt.isEmpty { opusPts.insert(pt) }
    }
    guard !opusPts.isEmpty else { return sdp }

    var fmtpPresent = Set<String>()
    lines = lines.map { line in
      guard line.hasPrefix("a=fmtp:") else { return line }
      let rest = line.dropFirst("a=fmtp:".count)
      let pt = String(rest.prefix(while: { $0.isNumber }))
      guard opusPts.contains(pt) else { return line }
      fmtpPresent.insert(pt)
      return "a=fmtp:\(pt) \(fmtpValue)"
    }

    var out: [String] = []
    out.reserveCapacity(lines.count + opusPts.count)
    for line in lines {
      out.append(line)
      let lower = line.lowercased()
      guard lower.hasPrefix("a=rtpmap:"), lower.contains("opus/48000") else { continue }
      let rest = line.dropFirst("a=rtpmap:".count)
      let pt = String(rest.prefix(while: { $0.isNumber }))
      guard opusPts.contains(pt), !fmtpPresent.contains(pt) else { continue }
      out.append("a=fmtp:\(pt) \(fmtpValue)")
      fmtpPresent.insert(pt)
    }

    return out.joined(separator: "\r\n") + "\r\n"
  }
}

extension NativeWhipPublisher: RTCPeerConnectionDelegate {
  func peerConnection(_ peerConnection: RTCPeerConnection, didChange stateChanged: RTCSignalingState) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didAdd stream: RTCMediaStream) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didRemove stream: RTCMediaStream) {}
  func peerConnectionShouldNegotiate(_ peerConnection: RTCPeerConnection) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didChange newState: RTCIceConnectionState) {
    let label: String
    switch newState {
    case .new: label = "new"
    case .checking: label = "checking"
    case .connected: label = "connected"
    case .completed: label = "completed"
    case .failed: label = "failed"
    case .disconnected: label = "disconnected"
    case .closed: label = "closed"
    case .count: label = "count"
    @unknown default: label = "unknown"
    }
    iceState = label
    onIce?(label)
    if newState == .failed || newState == .disconnected {
      setState(.failed)
    } else if newState == .connected || newState == .completed {
      setState(.connected)
    }
  }
  func peerConnection(_ peerConnection: RTCPeerConnection, didChange newState: RTCIceGatheringState) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didGenerate candidate: RTCIceCandidate) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didRemove candidates: [RTCIceCandidate]) {}
  func peerConnection(_ peerConnection: RTCPeerConnection, didOpen dataChannel: RTCDataChannel) {}
}

/// Holds the last good interleaved PCM so mic hot-swap while live does not publish zeros.
final class PublishContinuityHold {
  private var samples: [Float] = []
  private var frames = 0
  private var channels = 0
  private(set) var forced = false

  func beginHold() { forced = true }
  func endHold() { forced = false }

  func store(interleaved: UnsafePointer<Float>, frames: Int, channels: Int) {
    guard frames > 0, channels > 0 else { return }
    let n = frames * channels
    samples = Array(UnsafeBufferPointer(start: interleaved, count: n))
    self.frames = frames
    self.channels = channels
  }

  /// Replay / stretch last good audio into dest. Returns false if nothing stored.
  @discardableResult
  func fill(frames: Int, channels: Int, into dest: UnsafeMutablePointer<Float>) -> Bool {
    guard self.frames > 0, self.channels > 0, !samples.isEmpty else { return false }
    let srcCh = self.channels
    let srcFrames = self.frames
    for f in 0..<frames {
      let srcF = min(f, srcFrames - 1)
      for c in 0..<channels {
        let srcC = min(c, srcCh - 1)
        dest[f * channels + c] = samples[srcF * srcCh + srcC]
      }
    }
    return true
  }

  var hasSamples: Bool { frames > 0 && !samples.isEmpty }
}

/// Overwrites WebRTC mic buffers with the native AVAudioEngine master mix.
final class MixCaptureInjector: NSObject, RTCAudioCustomProcessingDelegate {
  weak var engine: NativeAudioEngine?
  private var processCount = 0
  private var processSampleRate: Double = 48_000
  private var processChannels = 1
  let continuity = PublishContinuityHold()

  func beginHold() { continuity.beginHold() }
  func endHold() { continuity.endHold() }

  func audioProcessingInitialize(sampleRate sampleRateHz: Int, channels: Int) {
    processCount = 0
    if sampleRateHz > 0 {
      processSampleRate = Double(sampleRateHz)
    }
    if channels > 0 {
      processChannels = channels
    }
  }

  func audioProcessingProcess(audioBuffer: RTCAudioBuffer) {
    guard let engine else { return }
    let frames = Int(audioBuffer.frames)
    let chans = Int(audioBuffer.channels)
    guard frames > 0, chans > 0 else { return }

    // Prefer the native 48 kHz master mix. ADM alone is often silent when
    // AVAudioEngine holds exclusive access to Scarlett / Focusrite.
    var didInject = false
    if engine.availableMixFrames() >= 1 {
      let interleaved = UnsafeMutablePointer<Float>.allocate(capacity: frames * max(chans, 2))
      defer { interleaved.deallocate() }
      let filled = engine.readMixResampled(
        frames: frames,
        channels: chans,
        targetSampleRate: processSampleRate,
        into: interleaved
      )
      if filled > 0 {
        for c in 0..<chans {
          let dest = audioBuffer.rawBuffer(forChannel: c)
          for f in 0..<filled {
            dest[f] = interleaved[f * chans + c]
          }
          // Hold last sample on short underruns — zeroing made Listen sound empty
          // while Studio meters still moved.
          if filled < frames {
            let hold = dest[filled - 1]
            for f in filled..<frames {
              dest[f] = hold
            }
          }
        }
        continuity.store(interleaved: interleaved, frames: filled, channels: chans)
        didInject = true
        processCount += 1
      }
    }

    // During mic hot-swap the ring briefly drains and ADM is often silent / wrong device.
    // Replay last good master PCM instead of publishing zeros until the graph reattaches.
    let needsContinuity =
      !didInject && (continuity.forced || engine.inputHotSwapActive) && continuity.hasSamples
    if needsContinuity {
      let interleaved = UnsafeMutablePointer<Float>.allocate(capacity: frames * chans)
      defer { interleaved.deallocate() }
      if continuity.fill(frames: frames, channels: chans, into: interleaved) {
        for c in 0..<chans {
          let dest = audioBuffer.rawBuffer(forChannel: c)
          for f in 0..<frames {
            dest[f] = interleaved[f * chans + c]
          }
        }
        didInject = true
      }
    }

    // Mute / Mic / Master must control Listen. While live, ADM often bypasses
    // AVAudioEngine, so apply console gain here on the buffer that goes out.
    let scale = engine.publishOutputScale(didInjectMix: didInject)
    var acc: Float = 0
    var n = 0
    for c in 0..<chans {
      let dest = audioBuffer.rawBuffer(forChannel: c)
      if scale <= 0 {
        for f in 0..<frames { dest[f] = 0 }
      } else if scale != 1 {
        for f in 0..<frames {
          dest[f] *= scale
        }
      }
      for f in 0..<frames {
        let s = dest[f]
        acc += s * s
        n += 1
      }
    }
    engine.noteCaptureRms(sqrt(acc / Float(max(n, 1))))
  }

  func audioProcessingRelease() {}
}

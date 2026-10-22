import Foundation
import WebRTC

enum PublisherReconnectDecision {
  /// Matches Web Studio: a failed check is not proof of replacement.
  static func decide(owns: Bool, replaced: Bool, checkFailed: Bool) -> String {
    if checkFailed { return "recover" }
    if replaced && !owns { return "replaced" }
    return "recover"
  }
}

let publisherReplacedNotice =
  "This stream is now being published from another device or session."

enum WhipIcePolicy {
  enum Action: String {
    case ignore
    case connected
    case reconnect
  }

  static func action(
    ice: String,
    isCurrentConnection: Bool,
    stopping: Bool
  ) -> Action {
    if !isCurrentConnection || stopping { return .ignore }
    switch ice {
    case "connected", "completed":
      return .connected
    case "failed":
      return .reconnect
    default:
      return .ignore
    }
  }
}

/// WHIP publisher — Opus offer to MediaMTX, mix injected via capture post-processing.
final class NativeWhipPublisher: NSObject {
  enum State: String {
    case idle, connecting, connected, recovering, replaced, failed, stopped
  }

  private var factory: RTCPeerConnectionFactory?
  private var pc: RTCPeerConnection?
  private var audioTrack: RTCAudioTrack?
  private var resourceURL: URL?
  private let mixInjector = MixCaptureInjector()

  private(set) var state: State = .idle
  private(set) var iceState: String = "new"

  var onState: ((State, String?) -> Void)?
  var onIce: ((String) -> Void)?

  private weak var audioEngine: NativeAudioEngine?
  private var lastWhipUrl: String?
  private var claimUrl: String?
  private var authToken: String?
  private var publisherClaim: String?
  private var iceReconnectAttempts = 0
  private var disconnectGraceWorkItem: DispatchWorkItem?
  private var reconnectWorkItem: DispatchWorkItem?
  private var stopping = false
  private var didConnectOnce = false
  /// Admin Opus send cap. Stereo/Auto vs Mono is chosen separately.
  private var opusSendBitrate = 320_000

  private static let opusSendRates: Set<Int> = [128_000, 160_000, 192_000, 256_000, 320_000, 510_000]

  private static func clampedOpusRate(_ value: Int) -> Int {
    opusSendRates.contains(value) ? value : 320_000
  }

  func attach(engine: NativeAudioEngine) {
    audioEngine = engine
    mixInjector.engine = engine
  }

  func goLive(
    whipUrl: String,
    claimUrl: String? = nil,
    authToken: String? = nil,
    opusSendBitrate: Int? = nil,
    completion: @escaping (Error?) -> Void
  ) {
    if let claimUrl, !claimUrl.isEmpty {
      self.claimUrl = claimUrl
    }
    if let authToken, !authToken.isEmpty {
      self.authToken = authToken
    }
    if let opusSendBitrate {
      self.opusSendBitrate = Self.clampedOpusRate(opusSendBitrate)
    }
    goLive(whipUrl: whipUrl, resetReconnect: true, completion: completion)
  }

  private func goLive(
    whipUrl: String,
    resetReconnect: Bool,
    completion: @escaping (Error?) -> Void
  ) {
    // Always complete on main — Flutter MethodChannel + Timers require it.
    let finish: (Error?) -> Void = { error in
      DispatchQueue.main.async {
        completion(error)
      }
    }

    lastWhipUrl = whipUrl
    if resetReconnect {
      iceReconnectAttempts = 0
    }
    stop(clearUrl: false)
    setState(.connecting)

    // Keep the native mix flowing. Clearing the ring here used to guarantee
    // silent Listen until taps refilled — and ADM often never let them.
    audioEngine?.ensurePublishingGraph()

    // Never bypass APM — MixCaptureInjector runs in capture post-processing.
    // (bypassVoiceProcessing:true skipped the injector → silent / wrong mic on air.)
    let factory = SMMakePeerConnectionFactory(false, mixInjector)
    self.factory = factory

    // ADM is only a capture clock. Do not steal the Studio SOURCE mic.
    bindAdmInput(factory: factory)
    audioEngine?.ensurePublishingGraph()

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

    // Match web Studio CLEAN_AUDIO: do not run voice NS/AGC/HPF on capture.
    // MixCaptureInjector still overwrites ADM with the native mix.
    let audioConstraints = RTCMediaConstraints(
      mandatoryConstraints: nil,
      optionalConstraints: [
        "googEchoCancellation": "false",
        "googAutoGainControl": "false",
        "googNoiseSuppression": "false",
        "googHighpassFilter": "false",
        "googTypingNoiseDetection": "false",
        "echoCancellation": "false",
        "noiseSuppression": "false",
        "autoGainControl": "false",
      ]
    )
    let source = factory.audioSource(with: audioConstraints)
    let track = factory.audioTrack(with: source, trackId: "audio0")
    audioTrack = track

    // Match web Studio: high-bitrate Opus; stereo follows received mic channels.
    let encoding = RTCRtpEncodingParameters()
    encoding.isActive = true
    encoding.maxBitrateBps = NSNumber(value: opusSendBitrate)
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

  /// Apply Opus-only SDP. Do not fall back to the raw offer — that reintroduces G.722
  /// and Listen goes silent (48 kHz mix into a 16 kHz phone encoder).
  private func setLocalOffer(
    _ offer: RTCSessionDescription,
    on pc: RTCPeerConnection,
    completion: @escaping (Error?) -> Void
  ) {
    let stereo = audioEngine?.shouldPublishStereo() ?? false
    let tunedSdp = Self.preferHighQualityOpus(offer.sdp, stereo: stereo, bitrate: opusSendBitrate)
    guard tunedSdp.lowercased().contains("opus/48000") else {
      completion(
        NSError(
          domain: "WHIP",
          code: 6,
          userInfo: [NSLocalizedDescriptionKey: "Desktop WebRTC did not offer Opus. Update Studio Desktop, or use Chrome web Studio."]
        )
      )
      return
    }
    let tuned = RTCSessionDescription(type: offer.type, sdp: tunedSdp)
    pc.setLocalDescription(tuned) { error in
      DispatchQueue.main.async {
        completion(error)
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
        encoding.maxBitrateBps = NSNumber(value: opusSendBitrate)
        encoding.bitratePriority = 4.0
        encoding.networkPriority = .high
      }
      params.encodings = encodings
      sender.parameters = params
    }
  }

  private func bindAdmInput(factory: RTCPeerConnectionFactory) {
    let adm = factory.audioDeviceModule
    // Do not bind ADM to the Studio SOURCE device. AVAudioEngine already owns
    // that mic; stealing it stops master taps and Listen goes silent even when
    // someone is talking or a playlist is playing. MixCaptureInjector overwrites
    // whatever ADM captures with the native mix.
    _ = adm.initRecording()
    _ = adm.startRecording()
  }

  /// Release WebRTC's mic briefly so AVAudioEngine can rebind CoreAudio without contention.
  func pauseCaptureForDeviceSwap() {
    guard state == .connected || state == .connecting || state == .recovering, let factory else { return }
    mixInjector.beginHold()
    let adm = factory.audioDeviceModule
    _ = adm.stopRecording()
  }

  /// After Studio switches hardware, point ADM at the new mic and resume capture.
  func rebindAdmAfterDeviceSwap() {
    guard state == .connected || state == .connecting || state == .recovering, let factory else {
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
    stop(clearUrl: true)
  }

  private func stop(clearUrl: Bool) {
    stopping = true
    disconnectGraceWorkItem?.cancel()
    disconnectGraceWorkItem = nil
    reconnectWorkItem?.cancel()
    reconnectWorkItem = nil
    if clearUrl {
      lastWhipUrl = nil
      iceReconnectAttempts = 0
      didConnectOnce = false
      releaseClaim()
    }
    if let resourceURL {
      var req = URLRequest(url: resourceURL)
      req.httpMethod = "DELETE"
      URLSession.shared.dataTask(with: req).resume()
    }
    resourceURL = nil
    audioTrack = nil
    // Drop `pc` before close so ICE disconnected/failed from this teardown
    // cannot mark the next session failed.
    let oldPc = pc
    pc = nil
    oldPc?.close()
    factory = nil
    iceState = "closed"
    onIce?(iceState)
    setState(.idle)
    stopping = false
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
    let stereo = audioEngine?.shouldPublishStereo() ?? false
    // ICE gathering can put G.722 back on the m-line; strip again immediately before POST.
    let publishSdp = Self.preferHighQualityOpus(local.sdp, stereo: stereo, bitrate: opusSendBitrate)
    // Claim before the WHIP POST. MediaMTX closes the previous publisher as
    // soon as this offer is accepted, and that client checks ownership when
    // ICE fails.
    claimOwnership { [weak self] in
      self?.submitWhipOffer(url: url, publishSdp: publishSdp, completion: completion)
    }
  }

  private func submitWhipOffer(
    url: URL,
    publishSdp: String,
    completion: @escaping (Error?) -> Void
  ) {
    var req = URLRequest(url: url)
    req.httpMethod = "POST"
    req.setValue("application/sdp", forHTTPHeaderField: "Content-Type")
    req.setValue("application/sdp", forHTTPHeaderField: "Accept")
    req.timeoutInterval = 15
    req.httpBody = publishSdp.data(using: .utf8)

    URLSession.shared.dataTask(with: req) { [weak self] data, response, error in
      DispatchQueue.main.async {
        guard let self else { return }
        if let error {
          self.releaseClaim()
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
          self.releaseClaim()
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
              self.releaseClaim()
              self.setState(.failed)
              completion(error)
              return
            }
            if let pc = self.pc {
              self.applyMaxAudioBitrate(on: pc)
            }
            self.audioEngine?.ensurePublishingGraph()
            self.setState(.connected)
            completion(nil)
          }
        }
      }
    }.resume()
  }

  private func setState(_ next: State, message: String? = nil) {
    if next == .connected {
      didConnectOnce = true
      iceReconnectAttempts = 0
    }
    state = next
    onState?(next, message)
  }

  private func handleIce(_ peerConnection: RTCPeerConnection, _ newState: RTCIceConnectionState) {
    dispatchPrecondition(condition: .onQueue(.main))
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
    let isCurrent = peerConnection === pc
    iceState = label
    onIce?(label)

    let action = WhipIcePolicy.action(
      ice: label,
      isCurrentConnection: isCurrent,
      stopping: stopping
    )
    switch action {
    case .connected:
      disconnectGraceWorkItem?.cancel()
      disconnectGraceWorkItem = nil
      setState(.connected)
    case .reconnect:
      if state == .replaced || state == .stopped {
        break
      }
      if didConnectOnce {
        scheduleReconnect()
      } else {
        setState(.failed, message: "WHIP publish failed. Check network and try Go live again.")
      }
    case .ignore:
      if isCurrent, !stopping, newState == .disconnected, didConnectOnce {
        scheduleDisconnectGrace()
      }
    }
  }

  private func scheduleDisconnectGrace() {
    if state == .replaced || state == .stopped { return }
    disconnectGraceWorkItem?.cancel()
    let work = DispatchWorkItem { [weak self] in
      guard let self, !self.stopping, self.pc != nil else { return }
      if self.iceState == "disconnected" || self.iceState == "failed" {
        self.scheduleReconnect()
      }
    }
    disconnectGraceWorkItem = work
    DispatchQueue.main.asyncAfter(deadline: .now() + 2.5, execute: work)
  }

  private func scheduleReconnect() {
    if state == .replaced || state == .stopped { return }
    guard let url = lastWhipUrl, didConnectOnce else {
      setState(.failed, message: "Broadcast link dropped. Pause, then Resume.")
      return
    }
    if iceReconnectAttempts >= 5 {
      setState(
        .failed,
        message: "Broadcast link dropped. Pause, then Resume."
      )
      return
    }
    iceReconnectAttempts += 1
    disconnectGraceWorkItem?.cancel()
    disconnectGraceWorkItem = nil
    reconnectWorkItem?.cancel()
    let delay = min(8.0, 0.6 * pow(2, Double(iceReconnectAttempts - 1)))
    setState(.recovering, message: "Audio link dropped — reconnecting…")
    NSLog("[whip] ice reconnect attempt=%d delay=%.1f", iceReconnectAttempts, delay)
    let work = DispatchWorkItem { [weak self] in
      guard let self, let url = self.lastWhipUrl, !self.stopping else { return }
      if self.state == .replaced || self.state == .stopped { return }
      self.decideBeforeRepublish { decision in
        if self.state == .replaced || self.state == .stopped || self.stopping {
          return
        }
        if decision == "replaced" {
          self.markReplaced()
          return
        }
        self.goLive(whipUrl: url, resetReconnect: false) { error in
          if error != nil {
            self.scheduleReconnect()
          }
        }
      }
    }
    reconnectWorkItem = work
    DispatchQueue.main.asyncAfter(deadline: .now() + delay, execute: work)
  }

  private func markReplaced() {
    reconnectWorkItem?.cancel()
    reconnectWorkItem = nil
    disconnectGraceWorkItem?.cancel()
    disconnectGraceWorkItem = nil
    iceReconnectAttempts = 0
    stopping = true
    let oldPc = pc
    pc = nil
    oldPc?.close()
    resourceURL = nil
    factory = nil
    stopping = false
    setState(.replaced, message: publisherReplacedNotice)
  }

  private func claimOwnership(completion: @escaping () -> Void) {
    let claim = UUID().uuidString.lowercased()
    publisherClaim = claim
    postClaim(intent: "claim", claim: claim) { _ in
      completion()
    }
  }

  private func releaseClaim() {
    guard let claim = publisherClaim else { return }
    publisherClaim = nil
    postClaim(intent: "release", claim: claim) { _ in }
  }

  /// Check failure, including a missing claim endpoint, stays on the recover path.
  private func decideBeforeRepublish(completion: @escaping (String) -> Void) {
    guard let claim = publisherClaim else {
      completion("recover")
      return
    }
    postClaim(intent: "check", claim: claim) { result in
      switch result {
      case .failure:
        completion(
          PublisherReconnectDecision.decide(owns: false, replaced: false, checkFailed: true)
        )
      case .success(let json):
        completion(
          PublisherReconnectDecision.decide(
            owns: json["owns"] as? Bool ?? false,
            replaced: json["replaced"] as? Bool ?? false,
            checkFailed: false
          )
        )
      }
    }
  }

  private func postClaim(
    intent: String,
    claim: String,
    completion: @escaping (Result<[String: Any], Error>) -> Void
  ) {
    guard let claimUrl, !claimUrl.isEmpty,
          let token = authToken, !token.isEmpty,
          let url = URL(string: claimUrl)
    else {
      completion(.failure(NSError(domain: "WHIP", code: 10)))
      return
    }
    var req = URLRequest(url: url)
    req.httpMethod = "POST"
    req.timeoutInterval = 8
    req.setValue("application/json", forHTTPHeaderField: "Content-Type")
    req.setValue("application/json", forHTTPHeaderField: "Accept")
    req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
    req.httpBody = try? JSONSerialization.data(withJSONObject: [
      "claim": claim,
      "intent": intent,
    ])
    URLSession.shared.dataTask(with: req) { data, response, error in
      DispatchQueue.main.async {
        if let error {
          completion(.failure(error))
          return
        }
        let code = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200..<300).contains(code),
              let data,
              let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
        else {
          completion(.failure(NSError(domain: "WHIP", code: code)))
          return
        }
        completion(.success(json))
      }
    }.resume()
  }

  /// Drop G.722 / PCMU / PCMA from the audio m-line (matches web `stripNonOpusAudioCodecs`).
  static func stripNonOpusAudioCodecs(_ sdp: String) -> String {
    var normalized = sdp.replacingOccurrences(of: "\r\n", with: "\n")
    normalized = normalized.replacingOccurrences(of: "\r", with: "\n")
    while normalized.hasSuffix("\n") {
      normalized.removeLast()
    }
    let lines = normalized.components(separatedBy: "\n")

    var opusPts = Set<String>()
    for line in lines {
      let lower = line.lowercased()
      guard lower.hasPrefix("a=rtpmap:"), lower.contains("opus/48000") else { continue }
      let rest = line.dropFirst("a=rtpmap:".count)
      let pt = String(rest.prefix(while: { $0.isNumber }))
      if !pt.isEmpty { opusPts.insert(pt) }
    }
    guard !opusPts.isEmpty else { return sdp }

    var inAudio = false
    var out: [String] = []
    out.reserveCapacity(lines.count)
    for line in lines {
      if line.hasPrefix("m=audio ") {
        inAudio = true
        let parts = line.split(separator: " ", omittingEmptySubsequences: false).map(String.init)
        let head = Array(parts.prefix(3))
        let pts = parts.dropFirst(3).filter { opusPts.contains($0) }
        out.append((head + pts).joined(separator: " "))
        continue
      }
      if line.hasPrefix("m=") {
        inAudio = false
      }
      if inAudio {
        let prefixes = ["a=rtpmap:", "a=fmtp:", "a=rtcp-fb:"]
        if let prefix = prefixes.first(where: { line.hasPrefix($0) }) {
          let rest = line.dropFirst(prefix.count)
          let pt = String(rest.prefix(while: { $0.isNumber }))
          if !pt.isEmpty, !opusPts.contains(pt) {
            continue
          }
        }
      }
      out.append(line)
    }
    return out.joined(separator: "\r\n") + "\r\n"
  }

  /// Same Opus fmtp as web Studio (`preferHighQualityOpus` in studio.js).
  static func preferHighQualityOpus(_ sdp: String, stereo: Bool = false, bitrate: Int = 320_000) -> String {
    let stripped = stripNonOpusAudioCodecs(sdp)
    let bitrate = clampedOpusRate(bitrate)
    let flag = stereo ? 1 : 0
    let fmtpValue =
      "minptime=20;useinbandfec=1;usedtx=0;stereo=\(flag);sprop-stereo=\(flag);maxaveragebitrate=\(bitrate);maxplaybackrate=48000"

    var normalized = stripped.replacingOccurrences(of: "\r\n", with: "\n")
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
    DispatchQueue.main.async { [weak self] in
      self?.handleIce(peerConnection, newState)
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

/// WebRTC APM capture buffers are FloatS16 (float values in ±32768), not ±1.
/// Writing unit PCM here is inaudible on Listen while Studio meters still move.
enum WebRtcCapturePcm {
  static let floatS16: Float = 32768

  static func fromUnit(_ sample: Float) -> Float {
    max(-32767, min(32767, sample * floatS16))
  }

  static func toUnitRms(_ rms: Float) -> Float {
    rms > 4 ? rms / floatS16 : rms
  }
}

/// Overwrites WebRTC mic buffers with the native mix, then adds the software playlist.
final class MixCaptureInjector: NSObject, RTCAudioCustomProcessingDelegate {
  weak var engine: NativeAudioEngine?
  private var processCount = 0
  private var playlistLogCount = 0
  private var processSampleRate: Double = 48_000
  private var processChannels = 1
  let continuity = PublishContinuityHold()

  func beginHold() { continuity.beginHold() }
  func endHold() { continuity.endHold() }

  func audioProcessingInitialize(sampleRate sampleRateHz: Int, channels: Int) {
    processCount = 0
    playlistLogCount = 0
    if sampleRateHz > 0 {
      processSampleRate = Double(sampleRateHz)
    }
    if channels > 0 {
      processChannels = channels
    }
    NSLog("[whip] capture process sr=%d ch=%d", sampleRateHz, channels)
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
            dest[f] = WebRtcCapturePcm.fromUnit(interleaved[f * chans + c])
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
            dest[f] = WebRtcCapturePcm.fromUnit(interleaved[f * chans + c])
          }
        }
        didInject = true
      }
    }

    let left = audioBuffer.rawBuffer(forChannel: 0)
    let right = chans > 1 ? audioBuffer.rawBuffer(forChannel: 1) : left
    // Program: mic (mute × fader) → master, then add playlist × playlist × master.
    if engine.isMicMuted {
      for c in 0..<chans {
        let dest = audioBuffer.rawBuffer(forChannel: c)
        for f in 0..<frames { dest[f] = 0 }
      }
    } else if !didInject {
      let micGain = engine.publishMicFader
      if micGain != 1 {
        for c in 0..<chans {
          let dest = audioBuffer.rawBuffer(forChannel: c)
          for f in 0..<frames { dest[f] *= micGain }
        }
      }
    }
    let master = engine.publishMasterFader
    if master <= 0 {
      for c in 0..<chans {
        let dest = audioBuffer.rawBuffer(forChannel: c)
        for f in 0..<frames { dest[f] = 0 }
      }
    } else {
      for c in 0..<chans {
        let dest = audioBuffer.rawBuffer(forChannel: c)
        for f in 0..<frames { dest[f] *= master }
      }
    }
    let playlistRms = engine.injectPlaylist(
      left: left,
      right: right,
      frames: frames,
      sampleRate: processSampleRate,
      downmixToMono: chans == 1,
      destScale: WebRtcCapturePcm.floatS16
    )
    if playlistRms > 0.001, playlistLogCount < 3 {
      playlistLogCount += 1
      var destPeak: Float = 0
      for f in 0..<frames {
        destPeak = max(destPeak, abs(left[f]))
      }
      NSLog(
        "[whip] playlist inject rms=%f destPeak=%f master=%f frames=%d sr=%.0f ch=%d",
        playlistRms,
        destPeak,
        master,
        frames,
        processSampleRate,
        chans
      )
    }

    var acc: Float = 0
    var n = 0
    for c in 0..<chans {
      let dest = audioBuffer.rawBuffer(forChannel: c)
      for f in 0..<frames {
        let s = dest[f]
        acc += s * s
        n += 1
      }
    }
    engine.noteCaptureRms(WebRtcCapturePcm.toUnitRms(sqrt(acc / Float(max(n, 1)))))
  }

  func audioProcessingRelease() {}
}

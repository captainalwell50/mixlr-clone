import AVFoundation
import Cocoa
import FlutterMacOS
import Speech

/// Native mixer MethodChannel — AVAudioEngine + WHIP (no WebView).
final class MixerEnginePlugin: NSObject {
  private var channel: FlutterMethodChannel?
  private let audio = NativeAudioEngine()
  private let whip = NativeWhipPublisher()
  private let scriptureSpeech = ScriptureSpeechController()

  static func register(with registrar: FlutterPluginRegistrar, hostView: NSView) {
    let channel = FlutterMethodChannel(
      name: "soundmix/mixer_engine",
      binaryMessenger: registrar.messenger
    )
    let instance = MixerEnginePlugin()
    instance.channel = channel
    instance.whip.attach(engine: instance.audio)
    instance.scriptureSpeech.attach(engine: instance.audio)
    instance.bindCallbacks()
    channel.setMethodCallHandler(instance.handle)
  }

  private func bindCallbacks() {
    audio.onMeters = { [weak self] mic, playlist, master in
      self?.post([
        "type": "levels",
        "mic": mic,
        "playlist": playlist,
        "master": master,
      ])
    }
    audio.onTracks = { [weak self] tracks in
      let payload: [[String: Any]] = tracks.map {
        [
          "id": $0.id,
          "title": $0.title,
          "assetId": $0.assetId as Any,
          "ready": $0.ready,
          "playing": $0.playing,
          "currentTime": $0.currentTime,
          "duration": $0.duration,
        ]
      }
      self?.post(["type": "tracks", "tracks": payload])
    }
    audio.onDevices = { [weak self] devices, selected in
      let payload: [[String: Any]] = devices.map {
        ["deviceId": $0.deviceId, "label": $0.label]
      }
      self?.post([
        "type": "devices",
        "inputs": payload,
        "selected": selected as Any,
      ])
    }
    audio.onOutputs = { [weak self] devices, selected in
      let payload: [[String: Any]] = devices.map {
        ["deviceId": $0.deviceId, "label": $0.label]
      }
      self?.post([
        "type": "outputs",
        "outputs": payload,
        "selected": selected as Any,
      ])
    }
    audio.onStatus = { [weak self] message in
      self?.post(["type": "status", "message": message])
    }
    whip.onState = { [weak self] state in
      self?.post(["type": "publish", "state": state.rawValue])
    }
    whip.onIce = { [weak self] state in
      self?.post(["type": "ice", "state": state])
    }
    audio.onInputDeviceChanged = { [weak self] in
      // Re-point WebRTC ADM after Studio rebinds CoreAudio (live mic hot-swap).
      self?.whip.rebindAdmAfterDeviceSwap()
    }
    scriptureSpeech.onPartial = { [weak self] words, isFinal in
      self?.post([
        "type": "scriptureSpeech",
        "words": words,
        "final": isFinal,
      ])
    }
    scriptureSpeech.onStatus = { [weak self] status in
      self?.post([
        "type": "scriptureSpeechStatus",
        "status": status,
      ])
    }
    scriptureSpeech.onError = { [weak self] message in
      self?.post([
        "type": "scriptureSpeechError",
        "message": message,
      ])
    }
  }

  private func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "micPermissionStatus":
      result(["status": audio.micPermissionStatus()])

    case "requestMicAccess":
      // Only prompts the OS when status is notDetermined.
      audio.requestMicAccess { [weak self] granted in
        result([
          "granted": granted,
          "status": self?.audio.micPermissionStatus() ?? (granted ? "authorized" : "denied"),
        ])
      }

    case "startEngine", "load":
      do {
        try audio.start()
        post(["type": "ready"])
        result(nil)
      } catch {
        result(FlutterError(code: "engine", message: error.localizedDescription, details: nil))
      }

    case "armMic":
      let deviceId = (call.arguments as? [String: Any])?["deviceId"] as? String
      do {
        try audio.armMic(deviceId: deviceId)
        result(["ok": true, "selected": audio.selectedDeviceId as Any])
      } catch {
        result(FlutterError(code: "armMic", message: error.localizedDescription, details: nil))
      }

    case "listDevices":
      let devices = audio.listInputDevices()
      audio.onDevices?(devices, audio.selectedDeviceId)
      result([
        "inputs": devices.map { ["deviceId": $0.deviceId, "label": $0.label] },
        "selected": audio.selectedDeviceId as Any,
      ])

    case "setInputDevice":
      guard let deviceId = call.arguments as? String ?? (call.arguments as? [String: Any])?["deviceId"] as? String else {
        result(FlutterError(code: "bad_args", message: "deviceId required", details: nil))
        return
      }
      do {
        // Pause ADM first so Scarlett/Built-in can rebind under AVAudioEngine without
        // leaving the WHIP ring starved (live publish → silence bug).
        // Successful setInputDevice fires onInputDeviceChanged → rebindAdmAfterDeviceSwap.
        let live = whip.state == .connected || whip.state == .connecting
        if live {
          whip.pauseCaptureForDeviceSwap()
        }
        try audio.setInputDevice(deviceId)
        result(nil)
      } catch {
        if whip.state == .connected || whip.state == .connecting {
          whip.rebindAdmAfterDeviceSwap()
        }
        result(FlutterError(code: "input", message: error.localizedDescription, details: nil))
      }

    case "setOutputDevice":
      guard let deviceId = call.arguments as? String ?? (call.arguments as? [String: Any])?["deviceId"] as? String else {
        result(FlutterError(code: "bad_args", message: "deviceId required", details: nil))
        return
      }
      do {
        try audio.setOutputDevice(deviceId)
        result(["ok": true, "selected": audio.selectedOutputDeviceId as Any])
      } catch {
        result(FlutterError(code: "output", message: error.localizedDescription, details: nil))
      }

    case "listOutputs":
      let devices = audio.listOutputDevices()
      audio.onOutputs?(devices, audio.selectedOutputDeviceId)
      result([
        "outputs": devices.map { ["deviceId": $0.deviceId, "label": $0.label] },
        "selected": audio.selectedOutputDeviceId as Any,
      ])

    case "reloadDevices":
      audio.reloadDevices()
      result(nil)

    case "setGains":
      let args = call.arguments as? [String: Any] ?? [:]
      audio.setGains(
        mic: Self.floatArg(args["mic"]),
        playlist: Self.floatArg(args["playlist"]),
        master: Self.floatArg(args["master"])
      )
      result(nil)

    case "setMutes":
      let args = call.arguments as? [String: Any] ?? [:]
      audio.setMutes(mic: args["mic"] as? Bool, playlist: args["playlist"] as? Bool)
      result(nil)

    case "setCues":
      let args = call.arguments as? [String: Any] ?? [:]
      audio.setCues(mic: args["mic"] as? Bool, playlist: args["playlist"] as? Bool)
      result(nil)

    case "queueTrack":
      let args = call.arguments as? [String: Any] ?? [:]
      guard let id = args["id"] as? String,
            let title = args["title"] as? String,
            let url = args["url"] as? String else {
        result(FlutterError(code: "bad_args", message: "id/title/url required", details: nil))
        return
      }
      do {
        try audio.queueTrack(id: id, title: title, url: url, assetId: args["assetId"] as? Int)
        result(nil)
      } catch {
        result(FlutterError(code: "queue", message: error.localizedDescription, details: nil))
      }

    case "play":
      guard let id = call.arguments as? String else {
        result(FlutterError(code: "bad_args", message: "track id required", details: nil))
        return
      }
      do {
        try audio.play(id)
        result(nil)
      } catch {
        result(FlutterError(code: "play", message: error.localizedDescription, details: nil))
      }

    case "pause":
      guard let id = call.arguments as? String else {
        result(FlutterError(code: "bad_args", message: "track id required", details: nil))
        return
      }
      audio.pause(id)
      result(nil)

    case "restart":
      guard let id = call.arguments as? String else {
        result(FlutterError(code: "bad_args", message: "track id required", details: nil))
        return
      }
      do {
        try audio.restart(id)
        result(nil)
      } catch {
        result(FlutterError(code: "restart", message: error.localizedDescription, details: nil))
      }

    case "remove":
      guard let id = call.arguments as? String else {
        result(FlutterError(code: "bad_args", message: "track id required", details: nil))
        return
      }
      audio.removeTrack(id)
      result(nil)

    case "goLive":
      let whipUrl = (call.arguments as? String)
        ?? (call.arguments as? [String: Any])?["whipUrl"] as? String
      guard let whipUrl, !whipUrl.isEmpty else {
        result(FlutterError(code: "bad_args", message: "whipUrl required", details: nil))
        return
      }
      whip.goLive(whipUrl: whipUrl) { error in
        // Result must be on main (Flutter embedding).
        DispatchQueue.main.async {
          if let error {
            result(FlutterError(code: "goLive", message: error.localizedDescription, details: nil))
          } else {
            result(nil)
          }
        }
      }

    case "stopPublish":
      whip.stop()
      result(nil)

    case "startScriptureListen":
      let localeId = (call.arguments as? [String: Any])?["localeId"] as? String ?? "en_US"
      scriptureSpeech.start(localeId: localeId) { ok, message in
        DispatchQueue.main.async {
          if ok {
            result(["ok": true])
          } else {
            result(FlutterError(
              code: "scripture_speech",
              message: message ?? "Speech recognition unavailable",
              details: nil
            ))
          }
        }
      }

    case "stopScriptureListen":
      scriptureSpeech.stop()
      result(nil)

    case "dispose":
      scriptureSpeech.stop()
      whip.stop()
      audio.tearDown()
      result(nil)

    case "eval":
      // Legacy no-op — WebView path removed.
      result(nil)

    default:
      result(FlutterMethodNotImplemented)
    }
  }

  private func post(_ dict: [String: Any]) {
    guard JSONSerialization.isValidJSONObject(dict),
          let data = try? JSONSerialization.data(withJSONObject: dict),
          let s = String(data: data, encoding: .utf8) else { return }
    DispatchQueue.main.async { [weak self] in
      self?.channel?.invokeMethod("hostMessage", arguments: s)
    }
  }

  private static func floatArg(_ value: Any?) -> Float? {
    if let n = value as? NSNumber { return n.floatValue }
    if let d = value as? Double { return Float(d) }
    if let f = value as? Float { return f }
    if let i = value as? Int { return Float(i) }
    return nil
  }
}

/// Scripture listen uses the Studio mixer tap — never a second AVAudioEngine.
///
/// Apple's recognizer finalizes after a short pause (~2–3s). Recycle only after
/// a finished utterance — do not cancel a live task or results never arrive.
final class ScriptureSpeechController {
  private weak var audio: NativeAudioEngine?
  private var recognizer: SFSpeechRecognizer?
  private var request: SFSpeechAudioBufferRecognitionRequest?
  private var task: SFSpeechRecognitionTask?
  private var listening = false
  private var localeId = "en_US"
  private var restartWork: DispatchWorkItem?
  private var healthWork: DispatchWorkItem?
  private var generation = 0
  private var buffersAppended = 0
  private var peakRms: Float = 0
  private var taskStartedAt: TimeInterval = 0
  private var gotPartial = false
  private var didLogFormat = false
  private var speechConverter: AVAudioConverter?
  private var speechConverterFrom: AVAudioFormat?
  private let speechQueue = DispatchQueue(label: "soundmix.scripture.speech")
  private let requestLock = NSLock()

  var onPartial: ((String, Bool) -> Void)?
  var onStatus: ((String) -> Void)?
  var onError: ((String) -> Void)?

  func attach(engine: NativeAudioEngine) {
    audio = engine
  }

  func start(localeId: String, completion: @escaping (Bool, String?) -> Void) {
    self.localeId = localeId
    let begin: () -> Void = { [weak self] in
      guard let self else {
        completion(false, "Speech recognition unavailable")
        return
      }
      do {
        try self.audio?.ensureArmedForSpeech()
        self.audio?.setSpeechListening(true)
        self.prepareRecognizer()
        guard let recognizer = self.recognizer else {
          completion(false, "Speech recognition is not available on this Mac. Type a reference instead.")
          return
        }
        guard recognizer.isAvailable else {
          completion(
            false,
            "Speech recognition isn’t available right now (check network and Dictation in System Settings). Type a reference instead."
          )
          return
        }
        self.listening = true
        self.didLogFormat = false
        self.speechConverter = nil
        self.speechConverterFrom = nil
        self.ensureMicTap()
        let state = self.task?.state
        if state != .running && state != .starting {
          self.startTask(force: false)
        }
        self.onStatus?("listening")
        completion(true, nil)
      } catch {
        self.audio?.setSpeechListening(false)
        completion(
          false,
          "Could not enable the Studio microphone for speech. Pick a SOURCE on the mixer, allow Microphone in System Settings, then tap Listen."
        )
      }
    }

    let status = SFSpeechRecognizer.authorizationStatus()
    switch status {
    case .authorized:
      DispatchQueue.main.async(execute: begin)
    case .notDetermined:
      SFSpeechRecognizer.requestAuthorization { next in
        DispatchQueue.main.async {
          if next == .authorized {
            begin()
          } else {
            completion(
              false,
              "Speech Recognition permission denied. Enable Speech Recognition in System Settings → Privacy & Security, then tap Listen again."
            )
          }
        }
      }
    case .denied, .restricted:
      completion(
        false,
        "Speech Recognition permission denied. Enable Speech Recognition in System Settings → Privacy & Security, then tap Listen again."
      )
    @unknown default:
      completion(false, "Speech recognition unavailable on this Mac. Type a reference instead.")
    }
  }

  func stop() {
    listening = false
    restartWork?.cancel()
    restartWork = nil
    healthWork?.cancel()
    healthWork = nil
    generation += 1
    audio?.onMicBuffer = nil
    audio?.setSpeechListening(false)
    speechConverter = nil
    speechConverterFrom = nil
    requestLock.lock()
    let oldRequest = request
    let oldTask = task
    request = nil
    task = nil
    requestLock.unlock()
    oldRequest?.endAudio()
    if oldTask?.state == .running || oldTask?.state == .starting {
      oldTask?.cancel()
    }
    onStatus?("notListening")
  }

  private func ensureMicTap() {
    audio?.onMicBuffer = { [weak self] buffer in
      self?.appendMicBuffer(buffer)
    }
  }

  private func appendMicBuffer(_ buffer: AVAudioPCMBuffer) {
    // Tap buffers are reused — copy before leaving the realtime thread.
    // Never append raw multi-channel / high-rate PCM (SFSpeech hears silence).
    guard let copy = Self.copyPCM(buffer) else { return }
    speechQueue.async { [weak self] in
      self?.processCopiedBuffer(copy)
    }
  }

  private func processCopiedBuffer(_ buffer: AVAudioPCMBuffer) {
    guard listening, let converted = convertForSpeech(buffer) else { return }
    if !didLogFormat {
      didLogFormat = true
      NSLog(
        "[scripture-speech] mixer sr=%.0f ch=%u → speech sr=%.0f ch=%u frames=%u peak=%.4f",
        buffer.format.sampleRate,
        buffer.format.channelCount,
        converted.format.sampleRate,
        converted.format.channelCount,
        converted.frameLength,
        Self.peakAbs(converted)
      )
    }
    requestLock.lock()
    request?.append(converted)
    requestLock.unlock()
    buffersAppended += 1
    let peak = Self.peakAbs(converted)
    if peak > peakRms { peakRms = peak }
  }

  /// SFSpeech needs mono linear PCM at 8–48 kHz. Mixer taps are often 96 kHz / 8ch.
  /// Do NOT force 48→16 kHz — a per-buffer converter can emit silence and kill recognition.
  private func convertForSpeech(_ buffer: AVAudioPCMBuffer) -> AVAudioPCMBuffer? {
    guard let mono = Self.downmixToMono(buffer) else { return nil }
    let srcRate = mono.format.sampleRate
    if srcRate >= 8_000, srcRate <= 48_000 {
      return mono
    }
    return resampleToSpeechRate(mono)
  }

  private func resampleToSpeechRate(_ mono: AVAudioPCMBuffer) -> AVAudioPCMBuffer? {
    guard let destFormat = AVAudioFormat(
      commonFormat: .pcmFormatFloat32,
      sampleRate: 16_000,
      channels: 1,
      interleaved: false
    ) else { return nil }

    if speechConverter == nil || speechConverterFrom != mono.format {
      speechConverter = AVAudioConverter(from: mono.format, to: destFormat)
      speechConverterFrom = mono.format
    }
    guard let converter = speechConverter else { return nil }

    let ratio = destFormat.sampleRate / max(mono.format.sampleRate, 1)
    let outFrames = AVAudioFrameCount(max((Double(mono.frameLength) * ratio).rounded(.up) + 32, 1))
    guard let out = AVAudioPCMBuffer(pcmFormat: destFormat, frameCapacity: outFrames) else {
      return nil
    }
    var error: NSError?
    var consumed = false
    let status = converter.convert(to: out, error: &error) { _, outStatus in
      if consumed {
        outStatus.pointee = .noDataNow
        return nil
      }
      consumed = true
      outStatus.pointee = .haveData
      return mono
    }
    if status == .error || out.frameLength == 0 {
      NSLog(
        "[scripture-speech] resample failed status=%@ err=%@ — dropping buffer (sr=%.0f)",
        String(describing: status),
        error?.localizedDescription ?? "nil",
        mono.format.sampleRate
      )
      return nil
    }
    return out
  }

  /// Stereo: average L+R. Multi-channel interfaces often put the mic on bus 2+; pick loudest.
  static func downmixToMono(_ buffer: AVAudioPCMBuffer) -> AVAudioPCMBuffer? {
    let frames = Int(buffer.frameLength)
    let channels = Int(buffer.format.channelCount)
    guard frames > 0, channels > 0 else { return nil }

    guard let destFormat = AVAudioFormat(
      commonFormat: .pcmFormatFloat32,
      sampleRate: buffer.format.sampleRate > 0 ? buffer.format.sampleRate : 48_000,
      channels: 1,
      interleaved: false
    ) else { return nil }
    guard let out = AVAudioPCMBuffer(pcmFormat: destFormat, frameCapacity: buffer.frameCapacity) else {
      return nil
    }
    out.frameLength = buffer.frameLength
    guard let dst = out.floatChannelData?[0] else { return nil }

    if let src = buffer.floatChannelData {
      if channels == 1 {
        dst.update(from: src[0], count: frames)
      } else if channels == 2 {
        let l = src[0]
        let r = src[1]
        for f in 0..<frames {
          dst[f] = 0.5 * (l[f] + r[f])
        }
      } else {
        let best = loudestChannel(src, channels: channels, frames: frames)
        dst.update(from: src[best], count: frames)
      }
      return out
    }
    if let src = buffer.int16ChannelData {
      if channels == 1 {
        for f in 0..<frames {
          dst[f] = Float(src[0][f]) / 32768.0
        }
      } else if channels == 2 {
        for f in 0..<frames {
          dst[f] = 0.5 * (Float(src[0][f]) + Float(src[1][f])) / 32768.0
        }
      } else {
        let best = loudestChannelInt16(src, channels: channels, frames: frames)
        for f in 0..<frames {
          dst[f] = Float(src[best][f]) / 32768.0
        }
      }
      return out
    }
    return nil
  }

  static func copyPCM(_ buffer: AVAudioPCMBuffer) -> AVAudioPCMBuffer? {
    guard let copy = AVAudioPCMBuffer(pcmFormat: buffer.format, frameCapacity: buffer.frameCapacity) else {
      return nil
    }
    copy.frameLength = buffer.frameLength
    let frames = Int(buffer.frameLength)
    let channels = Int(buffer.format.channelCount)
    guard frames > 0, channels > 0 else { return copy }
    if let src = buffer.floatChannelData, let dst = copy.floatChannelData {
      for c in 0..<channels {
        dst[c].update(from: src[c], count: frames)
      }
      return copy
    }
    if let src = buffer.int16ChannelData, let dst = copy.int16ChannelData {
      for c in 0..<channels {
        dst[c].update(from: src[c], count: frames)
      }
      return copy
    }
    return nil
  }

  private static func loudestChannel(
    _ src: UnsafePointer<UnsafeMutablePointer<Float>>,
    channels: Int,
    frames: Int
  ) -> Int {
    var best = 0
    var bestAcc: Float = -1
    let step = max(1, frames / 64)
    for c in 0..<channels {
      var acc: Float = 0
      let ch = src[c]
      for i in stride(from: 0, to: frames, by: step) {
        let s = ch[i]
        acc += s * s
      }
      if acc > bestAcc {
        bestAcc = acc
        best = c
      }
    }
    return best
  }

  private static func loudestChannelInt16(
    _ src: UnsafePointer<UnsafeMutablePointer<Int16>>,
    channels: Int,
    frames: Int
  ) -> Int {
    var best = 0
    var bestAcc: Float = -1
    let step = max(1, frames / 64)
    for c in 0..<channels {
      var acc: Float = 0
      let ch = src[c]
      for i in stride(from: 0, to: frames, by: step) {
        let s = Float(ch[i])
        acc += s * s
      }
      if acc > bestAcc {
        bestAcc = acc
        best = c
      }
    }
    return best
  }

  static func peakAbs(_ buffer: AVAudioPCMBuffer) -> Float {
    guard let ch = buffer.floatChannelData else { return 0 }
    let frames = Int(buffer.frameLength)
    guard frames > 0 else { return 0 }
    var peak: Float = 0
    let step = max(1, frames / 32)
    for i in stride(from: 0, to: frames, by: step) {
      peak = max(peak, abs(ch[0][i]))
    }
    return peak
  }

  private func prepareRecognizer() {
    recognizer = Self.resolveRecognizer(localeId: localeId)
  }

  static func resolveRecognizer(localeId: String) -> SFSpeechRecognizer? {
    let normalized = localeId.replacingOccurrences(of: "-", with: "_")
    var seen = Set<String>()
    var candidates: [Locale] = [
      Locale(identifier: normalized),
      Locale.current,
      Locale(identifier: "en_US"),
    ]
    for locale in candidates {
      let id = locale.identifier
      if seen.contains(id) { continue }
      seen.insert(id)
      if let rec = SFSpeechRecognizer(locale: locale), rec.isAvailable {
        return rec
      }
    }
    if let rec = SFSpeechRecognizer(), rec.isAvailable {
      return rec
    }
    return SFSpeechRecognizer(locale: Locale(identifier: normalized))
      ?? SFSpeechRecognizer(locale: Locale(identifier: "en_US"))
      ?? SFSpeechRecognizer()
  }

  private func startTask(force: Bool = false) {
    let state = task?.state
    if !force && (state == .running || state == .starting) { return }

    requestLock.lock()
    let oldRequest = request
    let oldTask = task
    request = nil
    task = nil
    requestLock.unlock()
    oldRequest?.endAudio()
    if oldTask?.state == .running || oldTask?.state == .starting {
      oldTask?.cancel()
    }

    let next = SFSpeechAudioBufferRecognitionRequest()
    next.shouldReportPartialResults = true
    next.taskHint = .dictation
    next.contextualStrings = Self.scriptureContextPhrases
    if #available(macOS 13, *) {
      next.addsPunctuation = false
    }
    requestLock.lock()
    request = next
    requestLock.unlock()
    generation += 1
    let gen = generation
    buffersAppended = 0
    peakRms = 0
    gotPartial = false
    taskStartedAt = Date().timeIntervalSince1970
    armHealthCheck()
    task = recognizer?.recognitionTask(with: next) { [weak self] result, error in
      guard let self, self.listening, self.generation == gen else { return }
      if let result {
        let words = result.bestTranscription.formattedString
        if !words.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
          self.gotPartial = true
        }
        DispatchQueue.main.async {
          self.onPartial?(words, result.isFinal)
        }
        if result.isFinal {
          DispatchQueue.main.async { [weak self] in
            self?.scheduleRestart(afterUtterance: true)
          }
        }
        return
      }
      if let error {
        let ns = error as NSError
        if self.isPermanentSpeechError(ns) {
          DispatchQueue.main.async {
            guard self.listening else { return }
            self.listening = false
            self.restartWork?.cancel()
            self.healthWork?.cancel()
            self.audio?.onMicBuffer = nil
            self.audio?.setSpeechListening(false)
            self.onError?(self.humanSpeechError(ns))
            self.onStatus?("notListening")
          }
          return
        }
        let age = Date().timeIntervalSince1970 - self.taskStartedAt
        let finished = self.task?.state == .completed || self.task?.state == .canceling
        // Do not recycle a live task — that was dropping every transcript.
        if finished || (age >= 2 && !self.gotPartial && (ns.code == 203 || ns.code == 1110)) {
          DispatchQueue.main.async { [weak self] in
            self?.scheduleRestart(afterUtterance: false)
          }
        }
      }
    }
  }

  private func armHealthCheck() {
    healthWork?.cancel()
    let work = DispatchWorkItem { [weak self] in
      self?.checkHealth()
    }
    healthWork = work
    DispatchQueue.main.asyncAfter(deadline: .now() + 2.5, execute: work)
  }

  private func checkHealth() {
    guard listening, !gotPartial else { return }
    if buffersAppended == 0 {
      onError?(
        "Microphone audio isn’t reaching speech recognition. Enable the Studio microphone (SOURCE), allow Microphone in System Settings, then tap Listen."
      )
      do {
        try audio?.ensureArmedForSpeech()
        audio?.setSpeechListening(true)
        ensureMicTap()
      } catch {
        return
      }
      scheduleRestart(afterUtterance: false)
      return
    }
    if peakRms < 0.0015 {
      onError?(
        "The Studio microphone is silent. Unmute SOURCE, pick the correct input, raise the fader, then speak a reference."
      )
      return
    }
    // Audio is flowing with level — wait for Apple to return words.
  }

  private static let scriptureContextPhrases: [String] = [
    "Genesis", "Exodus", "Leviticus", "Numbers", "Deuteronomy",
    "Joshua", "Judges", "Ruth", "Samuel", "Kings", "Chronicles",
    "Ezra", "Nehemiah", "Esther", "Job", "Psalm", "Psalms", "Proverbs",
    "Ecclesiastes", "Isaiah", "Jeremiah", "Lamentations", "Ezekiel", "Daniel",
    "Hosea", "Joel", "Amos", "Obadiah", "Jonah", "Micah", "Nahum",
    "Habakkuk", "Zephaniah", "Haggai", "Zechariah", "Malachi",
    "Matthew", "Mark", "Luke", "John", "Acts", "Romans", "Corinthians",
    "Galatians", "Ephesians", "Philippians", "Colossians", "Thessalonians",
    "Timothy", "Titus", "Philemon", "Hebrews", "James", "Peter", "Jude",
    "Revelation", "chapter", "verse", "John 3:16", "Psalm 23",
  ]

  private func isPermanentSpeechError(_ ns: NSError) -> Bool {
    let msg = ns.localizedDescription.lowercased()
    if msg.contains("not authorized") || msg.contains("permission") || msg.contains("not allowed") ||
        msg.contains("denied") || msg.contains("disabled") || msg.contains("restricted") {
      return true
    }
    return ns.code == 1700
  }

  private func humanSpeechError(_ ns: NSError) -> String {
    let msg = ns.localizedDescription.lowercased()
    if msg.contains("not authorized") || msg.contains("permission") || msg.contains("not allowed")
        || ns.code == 1700 {
      return "Speech Recognition permission denied. Enable Speech Recognition in System Settings → Privacy & Security, then tap Listen again."
    }
    return ns.localizedDescription
  }

  private func scheduleRestart(afterUtterance: Bool) {
    guard listening else { return }
    let state = task?.state
    if !afterUtterance && (state == .running || state == .starting) {
      return
    }
    restartWork?.cancel()
    let work = DispatchWorkItem { [weak self] in
      guard let self, self.listening else { return }
      self.startTask(force: true)
      self.onStatus?("listening")
    }
    restartWork = work
    // Match web Studio: brief delay so Apple can finish the prior utterance.
    DispatchQueue.main.asyncAfter(deadline: .now() + 0.35, execute: work)
  }
}

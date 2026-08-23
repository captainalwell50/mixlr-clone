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
/// Apple's recognizer still finalizes after a short pause (~2–3s). That must
/// not end the operator session: debounce-restart the task until stop().
final class ScriptureSpeechController {
  private weak var audio: NativeAudioEngine?
  private var recognizer: SFSpeechRecognizer?
  private var request: SFSpeechAudioBufferRecognitionRequest?
  private var task: SFSpeechRecognitionTask?
  private var listening = false
  private var localeId = "en_US"
  private var restartWork: DispatchWorkItem?
  private var generation = 0

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
        if self.audio != nil {
          try self.audio?.startIfNeeded()
        }
        self.prepareRecognizer()
        guard self.recognizer != nil else {
          completion(false, "English speech recognition is not available on this Mac.")
          return
        }
        self.listening = true
        self.ensureMicTap()
        let state = self.task?.state
        if state != .running && state != .starting {
          self.startTask()
        }
        self.onStatus?("listening")
        completion(true, nil)
      } catch {
        completion(false, error.localizedDescription)
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
            completion(false, "Allow Speech Recognition in System Settings → Privacy & Security.")
          }
        }
      }
    case .denied, .restricted:
      completion(false, "Allow Speech Recognition in System Settings → Privacy & Security.")
    @unknown default:
      completion(false, "Speech recognition unavailable on this Mac.")
    }
  }

  func stop() {
    listening = false
    restartWork?.cancel()
    restartWork = nil
    generation += 1
    audio?.onMicBuffer = nil
    let oldRequest = request
    let oldTask = task
    request = nil
    task = nil
    oldRequest?.endAudio()
    if oldTask?.state == .running || oldTask?.state == .starting {
      oldTask?.cancel()
    }
    onStatus?("notListening")
  }

  private func ensureMicTap() {
    audio?.onMicBuffer = { [weak self] buffer in
      self?.request?.append(buffer)
    }
  }

  private func prepareRecognizer() {
    let requested = Locale(identifier: localeId)
    recognizer = SFSpeechRecognizer(locale: requested) ?? SFSpeechRecognizer(locale: Locale(identifier: "en_US")) ?? SFSpeechRecognizer()
  }

  private func startTask() {
    let oldRequest = request
    let oldTask = task
    request = nil
    task = nil
    oldRequest?.endAudio()
    if oldTask?.state == .running || oldTask?.state == .starting {
      oldTask?.cancel()
    }

    let next = SFSpeechAudioBufferRecognitionRequest()
    next.shouldReportPartialResults = true
    next.taskHint = .dictation
    if #available(macOS 13, *) {
      next.addsPunctuation = true
    }
    request = next
    generation += 1
    let gen = generation
    task = recognizer?.recognitionTask(with: next) { [weak self] result, error in
      guard let self, self.listening, self.generation == gen else { return }
      var ended = false
      if let result {
        let words = result.bestTranscription.formattedString
        DispatchQueue.main.async {
          self.onPartial?(words, result.isFinal)
        }
        if result.isFinal {
          ended = true
        }
      }
      if let error {
        let ns = error as NSError
        if self.isPermanentSpeechError(ns) {
          DispatchQueue.main.async {
            guard self.listening else { return }
            self.listening = false
            self.restartWork?.cancel()
            self.audio?.onMicBuffer = nil
            self.onError?(error.localizedDescription)
            self.onStatus?("notListening")
          }
          return
        }
        ended = true
      }
      if ended {
        DispatchQueue.main.async { [weak self] in
          self?.scheduleRestart()
        }
      }
    }
  }

  private func isPermanentSpeechError(_ ns: NSError) -> Bool {
    let msg = ns.localizedDescription.lowercased()
    if msg.contains("not authorized") || msg.contains("permission") || msg.contains("not allowed") ||
        msg.contains("denied") || msg.contains("disabled") || msg.contains("restricted") {
      return true
    }
    return ns.code == 1700
  }

  private func scheduleRestart() {
    guard listening else { return }
    restartWork?.cancel()
    let work = DispatchWorkItem { [weak self] in
      guard let self, self.listening else { return }
      self.startTask()
      self.onStatus?("listening")
    }
    restartWork = work
    // Match web Studio: brief delay so Apple can finish the prior utterance.
    DispatchQueue.main.asyncAfter(deadline: .now() + 0.35, execute: work)
  }
}

extension NativeAudioEngine {
  fileprivate func startIfNeeded() throws {
    try start()
  }
}

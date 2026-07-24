import Cocoa
import FlutterMacOS

/// Native mixer MethodChannel — AVAudioEngine + WHIP (no WebView).
final class MixerEnginePlugin: NSObject {
  private var channel: FlutterMethodChannel?
  private let audio = NativeAudioEngine()
  private let whip = NativeWhipPublisher()

  static func register(with registrar: FlutterPluginRegistrar, hostView: NSView) {
    let channel = FlutterMethodChannel(
      name: "soundmix/mixer_engine",
      binaryMessenger: registrar.messenger
    )
    let instance = MixerEnginePlugin()
    instance.channel = channel
    instance.whip.attach(engine: instance.audio)
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
        try audio.setInputDevice(deviceId)
        result(nil)
      } catch {
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

    case "dispose":
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

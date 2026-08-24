import AudioToolbox
import AVFoundation
import CoreAudio
import Foundation

/// Native Core Audio mixer: mic + playlist → master mix (WHIP ring) + cue bus.
final class NativeAudioEngine {
  struct InputDevice {
    let deviceId: String
    let label: String
    let audioDeviceID: AudioDeviceID
  }

  struct TrackState {
    let id: String
    let title: String
    let assetId: Int?
    var ready: Bool
    var playing: Bool
    var currentTime: Double
    var duration: Double
  }

  private var engine = AVAudioEngine()
  private let micMixer = AVAudioMixerNode()
  private let playlistMixer = AVAudioMixerNode()
  private let masterMixer = AVAudioMixerNode()
  /// Headphones / cue bus (PFL). Fed by per-channel cue sends — not the program mix.
  private let cueMixer = AVAudioMixerNode()
  /// Mic strip → program (mute × fader). Cue send branches before this so muted mics are still cueable.
  private let micProgramSend = AVAudioMixerNode()
  /// Playlist strip → program (mute × fader).
  private let playlistProgramSend = AVAudioMixerNode()
  /// Mic → cue bus gain (1 when Mic CUE on, else 0). Pre-fader / pre-mute.
  private let micCueSend = AVAudioMixerNode()
  /// Playlist → cue bus gain (1 when Playlist CUE on, else 0). Pre-fader / pre-mute.
  private let playlistCueSend = AVAudioMixerNode()
  /// Master → headphones pad. Near-silent pull when cue is off; muted when cueing
  /// so HP hears only the cue bus (WHIP still taps master upstream at full level).
  private let programMonitor = AVAudioMixerNode()
  /// Always-on near-silent pull from the mic strip so meter/speech taps keep firing
  /// when SOURCE is muted and CUE is off (program+cue sends at 0 stop the graph).
  private let micTapPull = AVAudioMixerNode()

  private var playerNodes: [String: AVAudioPlayerNode] = [:]
  private var playerFiles: [String: AVAudioFile] = [:]
  private var trackMeta: [String: TrackState] = [:]
  private var tempFiles: [String: URL] = [:]

  private let bufferLock = NSLock()
  private var ring: [Float]
  private var ringWrite = 0
  private var ringRead = 0
  private var ringCount = 0
  private let ringCapacity: Int

  private var meterTimer: Timer?
  private(set) var micLevel: Float = 0
  private(set) var playlistLevel: Float = 0
  private(set) var masterLevel: Float = 0

  private var micFader: Float = 1
  private var playlistFader: Float = 1
  private var masterFader: Float = 1
  private var micMuted = false
  private var playlistMuted = false
  private var micCue = false
  private var playlistCue = false
  private(set) var armed = false
  private(set) var selectedDeviceId: String?
  private(set) var selectedOutputDeviceId: String? = "default"
  private var sessionReady = false
  private var nodesAttached = false
  private var micWired = false
  /// True when the micMixer meter/speech tap is installed.
  private var micMixerTapInstalled = false
  /// True when scripture speech falls back to the inputNode tap (micMixer install failed).
  private var speechFromInputNode = false
  /// Ring PCM is a full wet master mix (mic+playlist faders baked; master applied at inject).
  private var ringHasMasterGain = false
  /// Mute × mic × master — read from the WebRTC audio callback.
  private var cachedPublishGain: Float = 1
  /// Hardware / AVAudioEngine rate (Scarlett often 44.1 / 48 / 96 / 192).
  private var engineSampleRate: Double = 48_000
  /// WHIP ring is always stored at 48 kHz so WebRTC 10 ms pulls never underrun
  /// when the interface runs off-rate (symptom: hot local meters, silent Listen).
  private let publishSampleRate: Double = 48_000
  /// System default input before Studio last changed it — restored on tearDown so
  /// opening Desktop does not permanently steal the mic from another Studio on this Mac.
  private var previousSystemDefaultInput: AudioDeviceID?
  /// True while mic hardware is being swapped — WHIP injector holds last PCM instead of
  /// falling through to a silent ADM buffer (permanent −inf on Listen).
  private(set) var inputHotSwapActive = false
  private var hotSwapEndWorkItem: DispatchWorkItem?

  var onMeters: ((Float, Float, Float) -> Void)?
  var onTracks: (([TrackState]) -> Void)?
  var onDevices: (([InputDevice], String?) -> Void)?
  var onOutputs: (([InputDevice], String?) -> Void)?
  var onStatus: ((String) -> Void)?
  /// Fired after a successful input (re)bind so WHIP can re-point ADM at the new mic.
  var onInputDeviceChanged: (() -> Void)?
  /// Live mic PCM for scripture speech — must not start a second AVAudioEngine.
  var onMicBuffer: ((AVAudioPCMBuffer) -> Void)?
  /// While scripture Listen is active, keep a stronger output pull so mic taps stay live.
  private var speechListening = false
  /// Mixer graph stopped so speech_to_text can own the mic (Aug 9 path) without a second-engine crash.
  private var suspendedForSpeech = false
  private var resumeDeviceIdAfterSpeech: String?
  private var wasArmedBeforeSpeechSuspend = false
  /// Raw tap callbacks while Listen is armed (proves graph pull before SFSpeech conversion).
  private(set) var speechRawTapCount: Int = 0

  /// Label for the selected mic — used to bind WebRTC ADM on go-live.
  var selectedInputLabel: String? {
    guard let selectedDeviceId else { return nil }
    return listInputDevices().first(where: { $0.deviceId == selectedDeviceId })?.label
  }

  init() {
    // ~1.5s of stereo float @ 48kHz
    ringCapacity = 48_000 * 2 * 3 / 2
    ring = [Float](repeating: 0, count: ringCapacity)
  }

  deinit {
    tearDown()
  }

  /// Scripture listen needs a running input graph — `start()` only prepares the session.
  func ensureArmedForSpeech() throws {
    if suspendedForSpeech {
      // Resume first — shared SFSpeech cannot hear a suspended graph.
      try resumeAfterSpeechListen()
    }
    // Auto-pick a concrete mic when SOURCE is unset / No Input.
    let device = usableSpeechInputDeviceId()
    if armed && engine.isRunning && micWired {
      // Re-assert pull + taps so a prior mute/CUE-off starve does not leave Listen deaf.
      applyGains()
      if !micMixerTapInstalled && !speechFromInputNode {
        installMeterAndCaptureTaps()
      }
      return
    }
    try armMic(deviceId: device)
  }

  /// Prefer last SOURCE, else Built-in / MacBook, else system default — never "none".
  private func usableSpeechInputDeviceId() -> String? {
    if let selectedDeviceId, selectedDeviceId != "none", !selectedDeviceId.isEmpty {
      return selectedDeviceId
    }
    return preferredConcreteInputId() ?? "default"
  }

  func setSpeechListening(_ active: Bool) {
    speechListening = active
    if active { speechRawTapCount = 0 }
    applyGains()
    if active, armed, engine.isRunning {
      // Ensure the strip tap that feeds SFSpeech is alive the moment Listen arms.
      if !micMixerTapInstalled && !speechFromInputNode {
        installMeterAndCaptureTaps()
      }
    }
  }

  /// Reinstall meter/speech taps after Listen starts (safe if already installed).
  func refreshTapsForSpeech() {
    guard armed, engine.isRunning, !suspendedForSpeech else { return }
    installMeterAndCaptureTaps()
    applyGains()
  }

  /// Stop the Studio AVAudioEngine so speech_to_text can open the mic alone.
  /// Restores with [resumeAfterSpeechListen] when Listen ends — avoids two engines.
  func suspendForSpeechListen() {
    if suspendedForSpeech { return }
    suspendedForSpeech = true
    resumeDeviceIdAfterSpeech = selectedDeviceId
    wasArmedBeforeSpeechSuspend = armed
    speechListening = false
    onMicBuffer = nil
    speechRawTapCount = 0
    removeAllTapsSafely()
    if engine.isRunning {
      engine.stop()
    }
    var err: NSError?
    _ = SMCatchException(&err) { [self] in
      self.engine.disconnectNodeOutput(self.engine.inputNode)
    }
    micWired = false
    NSLog(
      "[scripture-speech] mixer suspended for speech_to_text device=%@",
      resumeDeviceIdAfterSpeech ?? "nil"
    )
    onStatus?("Mic paused for scripture Listen")
  }

  /// Rebuild the mixer graph after speech_to_text releases the mic.
  func resumeAfterSpeechListen() throws {
    guard suspendedForSpeech else { return }
    suspendedForSpeech = false
    let device = resumeDeviceIdAfterSpeech ?? selectedDeviceId ?? preferredConcreteInputId()
    resumeDeviceIdAfterSpeech = nil
    if wasArmedBeforeSpeechSuspend || (device != nil && device != "none") {
      try armMic(deviceId: device)
      NSLog("[scripture-speech] mixer resumed after speech_to_text device=%@", device ?? "nil")
      onStatus?("Mic armed — speak to see levels (CUE optional for headphones)")
    } else {
      armed = false
      onStatus?("Native mixer ready — enable microphone")
    }
    wasArmedBeforeSpeechSuspend = false
  }

  var isSuspendedForSpeech: Bool { suspendedForSpeech }

  /// Prepare session + device list. Engine starts only after mic is armed
  /// (avoids avfaudio -10875 from starting with a mismatched IO graph).
  func start() throws {
    attachNodesIfNeeded()
    sessionReady = true
    startMeters()
    let devices = listInputDevices()
    onDevices?(devices, selectedDeviceId)
    onOutputs?(listOutputDevices(), selectedOutputDeviceId)
    onStatus?("Native mixer ready — enable microphone")
  }

  func tearDown() {
    meterTimer?.invalidate()
    meterTimer = nil
    endInputHotSwap()
    stopPublishSide()
    for id in Array(playerNodes.keys) {
      removeTrack(id)
    }
    if engine.isRunning {
      engine.stop()
    }
    restoreSystemDefaultInputIfNeeded()
    sessionReady = false
    armed = false
    micWired = false
    micMixerTapInstalled = false
    speechFromInputNode = false
    speechListening = false
    suspendedForSpeech = false
    resumeDeviceIdAfterSpeech = nil
    wasArmedBeforeSpeechSuspend = false
    speechRawTapCount = 0
  }

  func stopPublishSide() {
    // Engine stays up for local monitoring.
  }

  // MARK: - Mic

  /// TCC status for the microphone. OS prompt only happens from `requestMicAccess`
  /// when status is `notDetermined` — never on every launch.
  func micPermissionStatus() -> String {
    switch AVCaptureDevice.authorizationStatus(for: .audio) {
    case .authorized:
      return "authorized"
    case .notDetermined:
      return "notDetermined"
    case .denied, .restricted:
      return "denied"
    @unknown default:
      return "denied"
    }
  }

  func requestMicAccess(completion: @escaping (Bool) -> Void) {
    switch AVCaptureDevice.authorizationStatus(for: .audio) {
    case .authorized:
      completion(true)
    case .notDetermined:
      // First grant only — macOS will not show this again once decided.
      AVCaptureDevice.requestAccess(for: .audio) { granted in
        DispatchQueue.main.async { completion(granted) }
      }
    default:
      completion(false)
    }
  }

  func armMic(deviceId: String?) throws {
    if !sessionReady { try start() }
    // Prefer explicit id, else last choice, else avoid virtual defaults (AirBeam).
    let resolved = deviceId ?? selectedDeviceId ?? preferredConcreteInputId()
    try rebuildAndStart(deviceId: resolved)
    armed = true
    onDevices?(listInputDevices(), selectedDeviceId)
    onOutputs?(listOutputDevices(), selectedOutputDeviceId)
    onStatus?("Mic armed — speak to see levels (CUE optional for headphones)")
  }

  func listInputDevices() -> [InputDevice] {
    var devices: [InputDevice] = []
    var seen = Set<AudioDeviceID>()
    let defaultID = defaultInputDeviceID()

    // One synthetic default — never rename every HAL device to this (caused duplicates).
    devices.append(
      InputDevice(
        deviceId: "default",
        label: "System Default Microphone",
        audioDeviceID: defaultID
      )
    )
    if defaultID != 0 {
      seen.insert(defaultID)
    }

    var size = UInt32(0)
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDevices,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    guard AudioObjectGetPropertyDataSize(AudioObjectID(kAudioObjectSystemObject), &address, 0, nil, &size) == noErr else {
      return devices
    }
    let count = Int(size) / MemoryLayout<AudioDeviceID>.stride
    var ids = [AudioDeviceID](repeating: 0, count: count)
    guard AudioObjectGetPropertyData(AudioObjectID(kAudioObjectSystemObject), &address, 0, nil, &size, &ids) == noErr else {
      return devices
    }

    // AVFoundation discovery lists true capture sources (mics). HAL also reports
    // speakers/aggregates with phantom input buses — cross-check UIDs when present.
    let captureMicUIDs = captureMicrophoneUIDs()

    var hardware: [InputDevice] = []
    for id in ids {
      guard id != 0, !seen.contains(id) else { continue }
      guard isSelectableInputDevice(id, captureMicUIDs: captureMicUIDs) else { continue }
      let name = deviceName(id) ?? "Input \(id)"
      seen.insert(id)
      hardware.append(InputDevice(deviceId: String(id), label: name, audioDeviceID: id))
    }

    // Also surface the current default under its real name (Built-in, Scarlett, …)
    // so users can pin that hardware explicitly (in addition to "System Default").
    if defaultID != 0 {
      let realName = deviceName(defaultID) ?? ""
      if !realName.isEmpty,
         isSelectableInputDevice(defaultID, captureMicUIDs: captureMicUIDs),
         !hardware.contains(where: { $0.audioDeviceID == defaultID }) {
        hardware.insert(
          InputDevice(deviceId: String(defaultID), label: realName, audioDeviceID: defaultID),
          at: 0
        )
      }
    }

    hardware.sort {
      $0.label.localizedCaseInsensitiveCompare($1.label) == .orderedAscending
    }
    devices.append(contentsOf: hardware)
    return devices
  }

  func setInputDevice(_ deviceId: String) throws {
    // Pin UI selection immediately so the dropdown can't snap back to System Default.
    selectedDeviceId = deviceId
    onDevices?(listInputDevices(), selectedDeviceId)

    if deviceId == "none" {
      disarmInput()
      onStatus?("No Input")
      onInputDeviceChanged?()
      return
    }

    do {
      // While already armed (especially ON AIR), prefer a surgical hot-swap so the
      // master→WHIP ring is reattached instead of orphaned by a full graph teardown.
      if micWired {
        try hotSwapInputDevice(deviceId: deviceId)
      } else {
        try rebuildAndStart(deviceId: deviceId)
      }
      armed = true
      let devices = listInputDevices()
      let label = devices.first(where: { $0.deviceId == deviceId })?.label ?? deviceId
      onDevices?(devices, selectedDeviceId)
      onOutputs?(listOutputDevices(), selectedOutputDeviceId)
      onStatus?("Input: \(label)")
      onInputDeviceChanged?()
    } catch {
      endInputHotSwap()
      // Keep the user's choice even if the graph briefly fails — retry still uses it.
      onDevices?(listInputDevices(), selectedDeviceId)
      throw error
    }
  }

  /// Mixlr-style “No Input” — stop pulling the mic without tearing the whole session down.
  private func disarmInput() {
    removeAllTapsSafely()
    if engine.isRunning {
      engine.stop()
    }
    engine.disconnectNodeOutput(engine.inputNode)
    micWired = false
    armed = false
    micLevel = 0
    ensureVoiceProcessingDisabled()
  }

  func reloadDevices() {
    onDevices?(listInputDevices(), selectedDeviceId)
    onOutputs?(listOutputDevices(), selectedOutputDeviceId)
    onStatus?("Device list refreshed")
  }

  func listOutputDevices() -> [InputDevice] {
    var devices: [InputDevice] = []
    let defaultID = defaultOutputDeviceID()
    devices.append(
      InputDevice(deviceId: "default", label: "Default output", audioDeviceID: defaultID)
    )
    var seen: Set<AudioDeviceID> = defaultID != 0 ? [defaultID] : []

    var size = UInt32(0)
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDevices,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    guard AudioObjectGetPropertyDataSize(AudioObjectID(kAudioObjectSystemObject), &address, 0, nil, &size) == noErr else {
      return devices
    }
    let count = Int(size) / MemoryLayout<AudioDeviceID>.stride
    var ids = [AudioDeviceID](repeating: 0, count: count)
    guard AudioObjectGetPropertyData(AudioObjectID(kAudioObjectSystemObject), &address, 0, nil, &size, &ids) == noErr else {
      return devices
    }

    var hardware: [InputDevice] = []
    for id in ids {
      guard id != 0, !seen.contains(id) else { continue }
      guard isSelectableOutputDevice(id) else { continue }
      let name = deviceName(id) ?? "Output \(id)"
      seen.insert(id)
      hardware.append(InputDevice(deviceId: String(id), label: name, audioDeviceID: id))
    }
    if defaultID != 0 {
      let realName = deviceName(defaultID) ?? ""
      if !realName.isEmpty,
         isSelectableOutputDevice(defaultID),
         !hardware.contains(where: { $0.audioDeviceID == defaultID }) {
        hardware.insert(
          InputDevice(deviceId: String(defaultID), label: realName, audioDeviceID: defaultID),
          at: 0
        )
      }
    }
    hardware.sort {
      $0.label.localizedCaseInsensitiveCompare($1.label) == .orderedAscending
    }
    devices.append(contentsOf: hardware)
    return devices
  }

  /// Mic picker: Core Audio input scope + AVFoundation capture sources.
  /// Drops aggregate/VP devices (`kAudioDeviceTransportTypeAggregate`) and
  /// speakers that only expose a phantom input bus.
  private func isSelectableInputDevice(_ id: AudioDeviceID, captureMicUIDs: Set<String>) -> Bool {
    guard deviceIsAlive(id) else { return false }
    let inCh = inputChannelCount(id)
    guard inCh > 0 else { return false }

    let transport = deviceTransportType(id)
    // Voice Processing / CADefault aggregates — see kAudioDeviceTransportTypeAggregate.
    if transport == kAudioDeviceTransportTypeAggregate { return false }

    let name = deviceName(id) ?? ""
    let lower = name.lowercased()
    // Name fallback for aggregates that mis-report transport on some OS builds.
    if isExcludedAggregateName(name) { return false }

    // Speakers / headphones are outputs even when HAL reports a phantom input bus.
    if lower.contains("speaker") { return false }
    if lower.contains("headphone") && !lower.contains("mic") { return false }

    if let uid = deviceUID(id), captureMicUIDs.contains(uid) {
      return true
    }

    let outCh = outputChannelCount(id)
    if outCh == 0 { return true }
    if looksLikeInputInterface(lower) { return true }
    // Virtual cables often expose both scopes — keep them as selectable inputs.
    if isVirtualAudioCable(lower) { return true }
    return false
  }

  /// Cue HP picker: real outputs only — never mics / aggregates.
  private func isSelectableOutputDevice(_ id: AudioDeviceID) -> Bool {
    guard deviceIsAlive(id) else { return false }
    let outCh = outputChannelCount(id)
    guard outCh > 0 else { return false }

    let transport = deviceTransportType(id)
    if transport == kAudioDeviceTransportTypeAggregate { return false }

    let name = deviceName(id) ?? ""
    if isExcludedAggregateName(name) { return false }

    let lower = name.lowercased()
    let inCh = inputChannelCount(id)
    if lower.contains("microphone") || lower.hasSuffix(" mic") || lower.contains(" mic ") {
      return false
    }
    if looksLikeInputInterface(lower), inCh > 0, outCh == 0 {
      return false
    }
    return true
  }

  /// AVFoundation lists devices that are valid audio *capture* sources.
  private func captureMicrophoneUIDs() -> Set<String> {
    if #available(macOS 14.0, *) {
      let session = AVCaptureDevice.DiscoverySession(
        deviceTypes: [.microphone, .externalUnknown],
        mediaType: .audio,
        position: .unspecified
      )
      return Set(session.devices.map(\.uniqueID))
    }
    // Deployment target is older than DeviceType.microphone — use legacy enum.
    return Set(AVCaptureDevice.devices(for: .audio).map(\.uniqueID))
  }

  private func isExcludedAggregateName(_ name: String) -> Bool {
    let lower = name.lowercased()
    if name.hasPrefix("CADefaultDeviceAggregate") { return true }
    if name.hasPrefix("VPAUAggregate") { return true }
    if lower.contains("vpauaggregate") { return true }
    if lower.contains("aggregate device") { return true }
    if lower.contains("aggregateaudio") { return true }
    return false
  }

  private func looksLikeInputInterface(_ lower: String) -> Bool {
    if lower.contains("mic") { return true }
    if lower.contains("built-in") { return true }
    if lower.contains("macbook") && lower.contains("microphone") { return true }
    if lower.contains("scarlett") || lower.contains("focusrite") { return true }
    if lower.contains("usb") && (lower.contains("audio") || lower.contains("interface")) {
      return true
    }
    if lower.contains("input") { return true }
    return false
  }

  private func isVirtualAudioCable(_ lower: String) -> Bool {
    lower.contains("airbeam")
      || lower.contains("blackhole")
      || lower.contains("loopback")
      || lower.contains("soundflower")
  }

  func setOutputDevice(_ deviceId: String) throws {
    selectedOutputDeviceId = deviceId
    onOutputs?(listOutputDevices(), selectedOutputDeviceId)
    let label = listOutputDevices().first(where: { $0.deviceId == deviceId })?.label ?? deviceId
    guard micWired else {
      onStatus?("Cue HP: \(label)")
      return
    }
    try rebuildAndStart(deviceId: selectedDeviceId)
    armed = true
    onStatus?("Cue HP: \(label)")
  }

  /// Prefer a real built-in / interface mic when system default is a virtual device.
  private func preferredConcreteInputId() -> String? {
    let inputs = listInputDevices()
    let defaultName = deviceName(defaultInputDeviceID()) ?? ""
    let defaultIsVirtual =
      defaultName.localizedCaseInsensitiveContains("AirBeam")
      || defaultName.localizedCaseInsensitiveContains("Aggregate")
      || defaultName.hasPrefix("CADefault")
    guard defaultIsVirtual || selectedDeviceId == nil else { return selectedDeviceId }
    let preferred = inputs.first(where: {
      $0.deviceId != "default"
        && ($0.label.localizedCaseInsensitiveContains("MacBook")
          || $0.label.localizedCaseInsensitiveContains("Built-in")
          || $0.label.localizedCaseInsensitiveContains("Scarlett")
          || $0.label.localizedCaseInsensitiveContains("Focusrite"))
    })
    return preferred?.deviceId ?? selectedDeviceId
  }

  // MARK: - Gains / mute / cue

  func setGains(mic: Float?, playlist: Float?, master: Float?) {
    if let mic { micFader = max(0, mic) }
    if let playlist { playlistFader = max(0, playlist) }
    if let master { masterFader = max(0, master) }
    // Drop buffered PCM captured at the previous fader so Listen follows immediately.
    clearMixRing()
    applyGains()
  }

  func setMutes(mic: Bool?, playlist: Bool?) {
    if let mic { micMuted = mic }
    if let playlist { playlistMuted = playlist }
    clearMixRing()
    applyGains()
  }

  func setCues(mic: Bool?, playlist: Bool?) {
    if let mic { micCue = mic }
    if let playlist { playlistCue = playlist }
    applyGains()
    if micCue || playlistCue {
      if !micWired {
        onStatus?("Cue armed — enable microphone to hear headphones")
      } else {
        let label = listOutputDevices()
          .first(where: { $0.deviceId == (selectedOutputDeviceId ?? "default") })?
          .label ?? "headphones"
        onStatus?("Cue → \(label)")
      }
    }
  }

  func clearMixRing(resetWetFlag: Bool = true) {
    bufferLock.lock()
    ringRead = 0
    ringWrite = 0
    ringCount = 0
    // During live input hot-swap keep wetMaster so continuity PCM still gets
    // master fader (not mic-mute×gain=0) until the new tap refills the ring.
    if resetWetFlag {
      ringHasMasterGain = false
    }
    bufferLock.unlock()
  }

  // MARK: - Playlist

  func queueTrack(id: String, title: String, url: String, assetId: Int?) throws {
    if !sessionReady { try start() }
    attachNodesIfNeeded()
    if playerNodes[id] != nil {
      removeTrack(id)
    }

    let localURL = try resolveAudioURL(url, trackId: id)
    let file = try AVAudioFile(forReading: localURL)
    let player = AVAudioPlayerNode()
    var attachErr: NSError?
    let attached = SMCatchException(&attachErr) { [self] in
      self.engine.attach(player)
    }
    guard attached else {
      throw attachErr ?? NSError(
        domain: "NativeAudio",
        code: 18,
        userInfo: [NSLocalizedDescriptionKey: "Could not attach playlist player."]
      )
    }
    // Only wire into playlist bus once the mix graph exists (after arm).
    // Never connect while the engine is running — stop → connect → start.
    if micWired {
      let wasRunning = engine.isRunning
      if wasRunning { engine.stop() }
      var connectErr: NSError?
      let connected = SMCatchException(&connectErr) { [self] in
        self.engine.connect(player, to: self.playlistMixer, format: file.processingFormat)
      }
      if wasRunning {
        try engine.start()
      }
      if !connected {
        throw connectErr ?? NSError(
          domain: "NativeAudio",
          code: 19,
          userInfo: [NSLocalizedDescriptionKey: "Could not wire playlist into mixer."]
        )
      }
    }
    playerNodes[id] = player
    playerFiles[id] = file
    trackMeta[id] = TrackState(
      id: id,
      title: title,
      assetId: assetId,
      ready: true,
      playing: false,
      currentTime: 0,
      duration: Double(file.length) / file.processingFormat.sampleRate
    )
    emitTracks()
  }

  func play(_ id: String) throws {
    guard let player = playerNodes[id], let file = playerFiles[id] else {
      throw NSError(domain: "NativeAudio", code: 1, userInfo: [NSLocalizedDescriptionKey: "Track not found"])
    }
    player.stop()
    file.framePosition = 0
    player.scheduleFile(file, at: nil) { [weak self] in
      DispatchQueue.main.async {
        self?.trackMeta[id]?.playing = false
        self?.emitTracks()
      }
    }
    player.play()
    trackMeta[id]?.playing = true
    emitTracks()
  }

  func pause(_ id: String) {
    playerNodes[id]?.pause()
    trackMeta[id]?.playing = false
    emitTracks()
  }

  func restart(_ id: String) throws {
    try play(id)
  }

  func removeTrack(_ id: String) {
    if let player = playerNodes.removeValue(forKey: id) {
      player.stop()
      engine.detach(player)
    }
    playerFiles.removeValue(forKey: id)
    trackMeta.removeValue(forKey: id)
    if let temp = tempFiles.removeValue(forKey: id) {
      try? FileManager.default.removeItem(at: temp)
    }
    emitTracks()
  }

  func listTracks() -> [TrackState] {
    Array(trackMeta.values)
  }

  // MARK: - WHIP ring buffer (stereo float interleaved)

  /// How many stereo frames are waiting in the WHIP ring.
  func availableMixFrames() -> Int {
    bufferLock.lock()
    defer { bufferLock.unlock() }
    return ringCount / 2
  }

  /// Map linear RMS → 0…1 meter without makeup gain.
  /// −60 dBFS = empty, 0 dBFS = clip.
  private func meterLevel(rms: Float) -> Float {
    guard rms > 1e-7 else { return 0 }
    let db = 20 * log10(rms)
    return max(0, min(1, (db + 60) / 60))
  }

  /// Update VU from the PCM that actually goes on-air (after fader/mute).
  func noteCaptureRms(_ rms: Float) {
    // True on-air level (no display attenuation) so faders aren't slammed to compensate.
    let next = meterLevel(rms: rms)
    if micMuted {
      micLevel = 0
      masterLevel = max(masterLevel * 0.5, next)
      return
    }
    micLevel = max(micLevel * 0.5, next)
    masterLevel = max(masterLevel * 0.5, next)
  }

  /// Scale for the WebRTC buffer. Mic ring is dry; playlist/master ring is wet
  /// (mic+playlist faders baked, master applied here).
  func publishOutputScale(didInjectMix: Bool) -> Float {
    bufferLock.lock()
    let gain = cachedPublishGain
    let wetMaster = ringHasMasterGain
    let master = max(0, masterFader)
    let muted = micMuted
    bufferLock.unlock()
    if didInjectMix, wetMaster {
      // Mic mute already silenced micMixer; playlist can still go out.
      return master
    }
    if muted || gain <= 0 { return 0 }
    return gain
  }

  var currentMixSampleRate: Double { publishSampleRate }

  /// Pull mix audio for WebRTC. Ring is stereo interleaved; downmixes to `channels`.
  /// Returns frames actually filled. Does **not** zero the destination on underrun
  /// (caller should leave the WebRTC ADM buffer untouched if 0).
  @discardableResult
  func readMixInterleaved(frames: Int, channels: Int, into dest: UnsafeMutablePointer<Float>) -> Int {
    bufferLock.lock()
    defer { bufferLock.unlock() }
    return readMixInterleavedUnlocked(frames: frames, channels: channels, into: dest)
  }

  /// Pull from the 48 kHz ring, resampling only if WebRTC's process rate differs.
  @discardableResult
  func readMixResampled(
    frames: Int,
    channels: Int,
    targetSampleRate: Double,
    into dest: UnsafeMutablePointer<Float>
  ) -> Int {
    bufferLock.lock()
    defer { bufferLock.unlock() }

    let srcRate = publishSampleRate
    let outCh = max(channels, 1)
    guard frames > 0, targetSampleRate > 0 else { return 0 }

    // Same rate — fast path (normal: both 48 kHz).
    if abs(srcRate - targetSampleRate) < 1.0 {
      return readMixInterleavedUnlocked(frames: frames, channels: outCh, into: dest)
    }

    let ratio = srcRate / targetSampleRate
    let srcFramesNeeded = Int(ceil(Double(frames) * ratio)) + 2
    let tmp = UnsafeMutablePointer<Float>.allocate(capacity: srcFramesNeeded * outCh)
    defer { tmp.deallocate() }
    let got = readMixInterleavedUnlocked(frames: srcFramesNeeded, channels: outCh, into: tmp)
    guard got > 0 else { return 0 }

    var filled = 0
    for f in 0..<frames {
      let srcPos = Double(f) * ratio
      let i0 = Int(srcPos)
      guard i0 < got else { break }
      let i1 = min(i0 + 1, got - 1)
      let frac = Float(srcPos - Double(i0))
      for c in 0..<outCh {
        let a = tmp[i0 * outCh + c]
        let b = tmp[i1 * outCh + c]
        dest[f * outCh + c] = a + (b - a) * frac
      }
      filled += 1
    }
    return filled
  }

  private func readMixInterleavedUnlocked(
    frames: Int,
    channels: Int,
    into dest: UnsafeMutablePointer<Float>
  ) -> Int {
    let outCh = max(channels, 1)
    var filled = 0
    for f in 0..<frames {
      guard ringCount >= 2 else { break }
      let l = ring[ringRead]
      ringRead = (ringRead + 1) % ringCapacity
      ringCount -= 1
      let r = ring[ringRead]
      ringRead = (ringRead + 1) % ringCapacity
      ringCount -= 1
      if outCh == 1 {
        dest[f] = 0.5 * (l + r)
      } else {
        dest[f * outCh] = l
        dest[f * outCh + 1] = r
        for c in 2..<outCh {
          dest[f * outCh + c] = l
        }
      }
      filled += 1
    }
    return filled
  }

  // MARK: - Private

  private func attachNodesIfNeeded() {
    guard !nodesAttached else { return }
    var err: NSError?
    let ok = SMCatchException(&err) { [self] in
      self.engine.attach(self.micMixer)
      self.engine.attach(self.playlistMixer)
      self.engine.attach(self.micProgramSend)
      self.engine.attach(self.playlistProgramSend)
      self.engine.attach(self.masterMixer)
      self.engine.attach(self.cueMixer)
      self.engine.attach(self.micCueSend)
      self.engine.attach(self.playlistCueSend)
      self.engine.attach(self.programMonitor)
      self.engine.attach(self.micTapPull)
    }
    nodesAttached = ok
    if !ok {
      onStatus?(err?.localizedDescription ?? "Mixer attach failed")
    }
  }

  /// AVAudioEngine nodes keep an owning engine pointer — must detach before
  /// replacing `engine`, or the next `attach` aborts the process.
  private func detachOwnedNodes() {
    var err: NSError?
    _ = SMCatchException(&err) { [self] in
      if self.nodesAttached {
        self.engine.detach(self.micMixer)
        self.engine.detach(self.playlistMixer)
        self.engine.detach(self.micProgramSend)
        self.engine.detach(self.playlistProgramSend)
        self.engine.detach(self.masterMixer)
        self.engine.detach(self.cueMixer)
        self.engine.detach(self.micCueSend)
        self.engine.detach(self.playlistCueSend)
        self.engine.detach(self.programMonitor)
        self.engine.detach(self.micTapPull)
      }
      for player in self.playerNodes.values {
        self.engine.detach(player)
      }
    }
    nodesAttached = false
  }

  /// Disconnect mix-bus edges before rewiring (keeps player nodes attached).
  private func disconnectMixBuses() {
    var err: NSError?
    _ = SMCatchException(&err) { [self] in
      self.engine.disconnectNodeOutput(self.engine.inputNode)
      self.engine.disconnectNodeOutput(self.micMixer)
      self.engine.disconnectNodeOutput(self.playlistMixer)
      self.engine.disconnectNodeOutput(self.micProgramSend)
      self.engine.disconnectNodeOutput(self.playlistProgramSend)
      self.engine.disconnectNodeOutput(self.micCueSend)
      self.engine.disconnectNodeOutput(self.playlistCueSend)
      self.engine.disconnectNodeOutput(self.masterMixer)
      self.engine.disconnectNodeOutput(self.cueMixer)
      self.engine.disconnectNodeOutput(self.programMonitor)
      self.engine.disconnectNodeOutput(self.micTapPull)
      for player in self.playerNodes.values {
        self.engine.disconnectNodeOutput(player)
      }
    }
  }

  /// Program: mic/playlist → program sends (mute×fader) → master → (WHIP tap) → programMonitor → HP.
  /// Cue: strip fan-out → cue sends (pre-mute) → cueMixer → HP.
  /// micTapPull: near-silent side-chain so mic taps stay live when muted + CUE off.
  /// Fan-out is mixer→mixer only (never from inputNode — that caused !dev / -10867).
  /// Wire sinks first so fan-out destinations are initialized before multi-tap connect.
  private func wireMixGraph(micFormat: AVAudioFormat) -> NSError? {
    var err: NSError?
    let wired = SMCatchException(&err) { [self] in
      // Engine must be stopped for graph surgery; prepare so AUHAL formats settle.
      if self.engine.isRunning {
        self.engine.stop()
      }
      self.engine.prepare()

      let busFormat = micFormat
      // Downstream first — avoids avfaudio `inNodeUpstream.IsInitialized()` on fan-out.
      self.engine.connect(self.micProgramSend, to: self.masterMixer, format: busFormat)
      self.engine.connect(self.playlistProgramSend, to: self.masterMixer, format: busFormat)
      self.engine.connect(self.micCueSend, to: self.cueMixer, format: busFormat)
      self.engine.connect(self.playlistCueSend, to: self.cueMixer, format: busFormat)
      // Master stays at unity into programMonitor so the WHIP tap (on master) is unaffected
      // by headphone cue ducking.
      self.engine.connect(self.masterMixer, to: self.programMonitor, format: busFormat)
      // nil into mainMixer lets the engine match hardware output rate.
      self.engine.connect(self.programMonitor, to: self.engine.mainMixerNode, format: nil)
      self.engine.connect(self.cueMixer, to: self.engine.mainMixerNode, format: nil)
      self.engine.connect(self.micTapPull, to: self.engine.mainMixerNode, format: nil)

      // Mic strip → program send + cue send (pre-fader / pre-mute cue) + silent tap pull.
      self.engine.connect(
        self.micMixer,
        to: [
          AVAudioConnectionPoint(node: self.micProgramSend, bus: 0),
          AVAudioConnectionPoint(node: self.micCueSend, bus: 0),
          AVAudioConnectionPoint(node: self.micTapPull, bus: 0),
        ],
        fromBus: 0,
        format: busFormat
      )
      // Playlist strip → program send + cue send.
      self.engine.connect(
        self.playlistMixer,
        to: [
          AVAudioConnectionPoint(node: self.playlistProgramSend, bus: 0),
          AVAudioConnectionPoint(node: self.playlistCueSend, bus: 0),
        ],
        fromBus: 0,
        format: busFormat
      )

      let activeInput = self.engine.inputNode
      // nil format lets the engine match the input node's HW format.
      self.engine.connect(activeInput, to: self.micMixer, format: nil)

      for (id, player) in self.playerNodes {
        if let file = self.playerFiles[id] {
          self.engine.connect(player, to: self.playlistMixer, format: file.processingFormat)
        }
      }
    }
    if wired { return nil }
    return err ?? NSError(
      domain: "NativeAudio",
      code: 11,
      userInfo: [
        NSLocalizedDescriptionKey:
          "Could not build mixer graph. Try System Default Microphone.",
      ]
    )
  }

  /// Drop the engine and reattach nodes — clears stuck AU "!dev" / -10867 state.
  private func resetEngineInstance() {
    removeAllTapsSafely()
    if engine.isRunning {
      engine.stop()
    }
    // Critical: detach before releasing the old engine. Re-attaching nodes that
    // still report another owningEngine throws com.apple.coreaudio.avfaudio and
    // terminates the app (uncaught).
    disconnectMixBuses()
    detachOwnedNodes()
    engine = AVAudioEngine()
    nodesAttached = false
    attachNodesIfNeeded()
    for player in playerNodes.values {
      var err: NSError?
      _ = SMCatchException(&err) { [self] in
        self.engine.attach(player)
      }
    }
    // Players are connected in wireMixGraph after the mix buses exist.
  }

  /// Rebuild full mix graph (program + cue PFL) and start capture.
  /// On failure, resetEngineInstance detaches nodes before replacing the engine.
  private func rebuildAndStart(deviceId: String?) throws {
    let preservePublish = micWired
    if preservePublish {
      beginInputHotSwap()
    }
    do {
      try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: true)
      if preservePublish {
        scheduleEndInputHotSwap()
      }
      return
    } catch {
      onStatus?("Resetting audio engine…")
      resetEngineInstance()
    }
    try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: false)
    if preservePublish {
      scheduleEndInputHotSwap()
    }
  }

  /// Hot-swap capture hardware while keeping playlist/master/WHIP pipeline continuity.
  /// Brief gap is OK (injector holds last PCM); permanent silence is not.
  private func hotSwapInputDevice(deviceId: String) throws {
    beginInputHotSwap()
    onStatus?("Switching input…")

    do {
      try hotSwapInputDeviceOnce(deviceId: deviceId, allowDeviceBind: true)
      scheduleEndInputHotSwap()
      return
    } catch {
      onStatus?("Input switch retry…")
      resetEngineInstance()
    }

    // Full rebuild fallback — still under continuity hold so Listen doesn't drop to −inf.
    try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: true)
    scheduleEndInputHotSwap()
  }

  private func hotSwapInputDeviceOnce(deviceId: String, allowDeviceBind: Bool) throws {
    attachNodesIfNeeded()
    removeAllTapsSafely()

    let devices = listInputDevices()
    if engine.isRunning {
      engine.stop()
    }

    // Drop only the capture edge first; rebuild mix buses if the new HW rate differs.
    var disconnectErr: NSError?
    _ = SMCatchException(&disconnectErr) { [self] in
      self.engine.disconnectNodeOutput(self.engine.inputNode)
    }

    var didChangeSystemDefault = false
    if deviceId != "default" {
      selectedDeviceId = deviceId
      if let match = devices.first(where: { $0.deviceId == deviceId }),
         match.audioDeviceID != 0 {
        selectedDeviceId = match.deviceId
        if allowDeviceBind {
          do {
            try setEngineInputDevice(match.audioDeviceID)
          } catch {
            do {
              try setSystemDefaultInput(match.audioDeviceID)
              didChangeSystemDefault = true
            } catch {
              throw NSError(
                domain: "NativeAudio",
                code: 16,
                userInfo: [
                  NSLocalizedDescriptionKey:
                    "Could not switch to \(match.label). Pick it in System Settings → Sound → Input, then retry.",
                ]
              )
            }
          }
        }
      } else {
        throw NSError(
          domain: "NativeAudio",
          code: 17,
          userInfo: [NSLocalizedDescriptionKey: "Microphone not found — pick another input."]
        )
      }
    } else {
      selectedDeviceId = "default"
      if allowDeviceBind, let preferred = preferredConcreteInputId(),
         let match = devices.first(where: { $0.deviceId == preferred }),
         match.audioDeviceID != 0 {
        try? setEngineInputDevice(match.audioDeviceID)
      }
    }

    if didChangeSystemDefault {
      // Stale AUHAL after system-default rewrite — recreate, then rewire full graph.
      resetEngineInstance()
      try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: false)
      return
    }

    // Keep cue / monitor output binding stable across input swaps.
    let outputs = listOutputDevices()
    let outId = selectedOutputDeviceId ?? "default"
    if outId != "default",
       let out = outputs.first(where: { $0.deviceId == outId }),
       out.audioDeviceID != 0 {
      try? setEngineOutputDevice(out.audioDeviceID)
    }

    ensureVoiceProcessingDisabled()

    guard let micFormat = waitForValidInputFormat() else {
      throw NSError(
        domain: "NativeAudio",
        code: 14,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Input HW format is invalid after device switch. Try System Default Microphone.",
        ]
      )
    }

    // Rewire mix buses to the new hardware rate so master taps keep feeding the 48 kHz ring.
    disconnectMixBuses()

    if let wireErr = wireMixGraph(micFormat: micFormat) {
      throw NSError(
        domain: "NativeAudio",
        code: 11,
        userInfo: [
          NSLocalizedDescriptionKey:
            wireErr.localizedDescription.localizedCaseInsensitiveContains("HW format")
            ? wireErr.localizedDescription
            : "Could not rebuild mixer after input switch. Try again or End and Go live.",
        ]
      )
    }

    applyGains()
    micWired = true
    engine.prepare()
    do {
      try engine.start()
    } catch {
      micWired = false
      throw NSError(
        domain: "NativeAudio",
        code: 12,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Could not restart audio after input switch (\(error.localizedDescription)).",
        ]
      )
    }

    // Drop stale PCM at the previous device rate; keep wetMaster for continuity scaling.
    clearMixRing(resetWetFlag: false)
    installMeterAndCaptureTaps()
    engineSampleRate = micFormat.sampleRate > 0 ? micFormat.sampleRate : engineSampleRate
  }

  private func beginInputHotSwap() {
    hotSwapEndWorkItem?.cancel()
    hotSwapEndWorkItem = nil
    inputHotSwapActive = true
  }

  private func endInputHotSwap() {
    hotSwapEndWorkItem?.cancel()
    hotSwapEndWorkItem = nil
    inputHotSwapActive = false
  }

  /// Keep continuity hold until the master tap has refilled the WHIP ring (or timeout).
  private func scheduleEndInputHotSwap() {
    hotSwapEndWorkItem?.cancel()
    let work = DispatchWorkItem { [weak self] in
      guard let self else { return }
      // Poll briefly for ring refill so Listen never sticks on held silence forever.
      var attempts = 0
      func poll() {
        attempts += 1
        if self.availableMixFrames() >= 48 || attempts >= 40 {
          self.inputHotSwapActive = false
          self.hotSwapEndWorkItem = nil
          return
        }
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.025) {
          poll()
        }
      }
      poll()
    }
    hotSwapEndWorkItem = work
    DispatchQueue.main.async(execute: work)
  }

  /// Always use plain HAL — Apple Voice Processing caused meter/route instability.
  private func ensureVoiceProcessingDisabled() {
    let input = engine.inputNode
    guard input.isVoiceProcessingEnabled else { return }
    do {
      try input.setVoiceProcessingEnabled(false)
    } catch {
      // Best-effort; plain graph continues either way.
    }
  }

  private func rebuildAndStartOnce(deviceId: String?, allowDeviceBind: Bool) throws {
    attachNodesIfNeeded()
    removeAllTapsSafely()

    let devices = listInputDevices()

    if engine.isRunning {
      engine.stop()
    }

    // Clear prior edges so HAL can rebind cleanly.
    disconnectMixBuses()

    // Prefer AU CurrentDevice bind so we do not rewrite the macOS system default
    // input (that steals the mic from a concurrent web Studio on another channel).
    // Fall back to system-default rebinding only when the engine bind fails — common
    // with AirBeam / virtual aggregate devices.
    var didChangeSystemDefault = false
    if let deviceId, deviceId != "default" {
      selectedDeviceId = deviceId
      if let match = devices.first(where: { $0.deviceId == deviceId }),
         match.audioDeviceID != 0 {
        selectedDeviceId = match.deviceId
        if allowDeviceBind {
          do {
            try setEngineInputDevice(match.audioDeviceID)
          } catch {
            do {
              try setSystemDefaultInput(match.audioDeviceID)
              didChangeSystemDefault = true
            } catch {
              onStatus?(
                "Could not switch to \(match.label). Pick it in System Settings → Sound → Input, then retry."
              )
            }
          }
        }
      } else {
        onStatus?("Microphone not found — using system default route")
      }
    } else if let preferred = preferredConcreteInputId() {
      // Follow system default for capture; only pin the engine graph to a concrete
      // device — never change the global macOS input just because we prefer Built-in.
      selectedDeviceId = preferred
      if allowDeviceBind,
         let match = devices.first(where: { $0.deviceId == preferred }),
         match.audioDeviceID != 0 {
        try? setEngineInputDevice(match.audioDeviceID)
      }
    } else {
      selectedDeviceId = "default"
    }

    // After changing the system default, the existing inputNode often keeps a stale /
    // zero HW format ("Input HW format is invalid"). Recreate the engine so AUHAL
    // attaches to the new default before we read formats or enable VP.
    if didChangeSystemDefault {
      resetEngineInstance()
    }

    // Bind cue / monitor output (pairs I/O so clean capture can pull mic without silence).
    let outputs = listOutputDevices()
    let outId = selectedOutputDeviceId ?? "default"
    if outId != "default",
       let out = outputs.first(where: { $0.deviceId == outId }),
       out.audioDeviceID != 0 {
      try? setEngineOutputDevice(out.audioDeviceID)
    } else if let builtinOut = outputs.first(where: {
      $0.deviceId != "default"
        && ($0.label.localizedCaseInsensitiveContains("MacBook")
          || $0.label.localizedCaseInsensitiveContains("Built-in")
          || $0.label.localizedCaseInsensitiveContains("Speaker"))
    }) {
      try? setEngineOutputDevice(builtinOut.audioDeviceID)
    }

    ensureVoiceProcessingDisabled()

    guard let micFormat = waitForValidInputFormat() else {
      throw NSError(
        domain: "NativeAudio",
        code: 14,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Input HW format is invalid. Choose System Default Microphone or another mic and try again.",
        ]
      )
    }

    if let wireErr = wireMixGraph(micFormat: micFormat) {
      let message = wireErr.localizedDescription
      if message.localizedCaseInsensitiveContains("HW format")
        || message.localizedCaseInsensitiveContains("hw format")
      {
        throw NSError(
          domain: "NativeAudio",
          code: 14,
          userInfo: [
            NSLocalizedDescriptionKey:
              "Input HW format is invalid. Choose System Default Microphone or another mic and try again.",
          ]
        )
      }
      throw wireErr
    }

    applyGains()
    micWired = true

    engine.prepare()
    do {
      try engine.start()
    } catch {
      micWired = false
      throw NSError(
        domain: "NativeAudio",
        code: 12,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Could not start audio engine (\(error.localizedDescription)). Check mic permission and try again.",
        ]
      )
    }

    installMeterAndCaptureTaps()
  }

  /// Volume 0 on the output edge stops the graph from pulling — taps go silent.
  /// Cue-off + armed: keep mainMixer at full pull so mic/master taps fire for meters
  /// and scripture Listen. programMonitor stays near-silent so HP doesn't hear program
  /// until CUE. Near-zero mainMixer (e.g. 0.001) leaves meters dark on macOS.
  /// Cue-on: full HP level for the cue bus.
  private var headphonesOutputVolume: Float {
    if micCue || playlistCue { return 1.0 }
    if micWired || speechListening { return 1.0 }
    return 0.001
  }

  private func removeAllTapsSafely() {
    var err: NSError?
    _ = SMCatchException(&err) { [self] in
      self.engine.inputNode.removeTap(onBus: 0)
    }
    _ = SMCatchException(&err) { [self] in
      self.micMixer.removeTap(onBus: 0)
    }
    _ = SMCatchException(&err) { [self] in
      self.playlistMixer.removeTap(onBus: 0)
    }
    _ = SMCatchException(&err) { [self] in
      self.masterMixer.removeTap(onBus: 0)
    }
  }

  private func installMeterAndCaptureTaps() {
    // Taps never fire if the graph is stopped — restart when the mic is supposed to be live.
    if micWired && !engine.isRunning && !suspendedForSpeech {
      do {
        engine.prepare()
        try engine.start()
        NSLog("[scripture-speech] restarted engine before installing taps")
      } catch {
        NSLog(
          "[scripture-speech] engine start before taps failed: %@",
          error.localizedDescription
        )
      }
    }
    removeAllTapsSafely()
    let tapFormat = engine.inputNode.outputFormat(forBus: 0)
    if tapFormat.sampleRate > 0 {
      engineSampleRate = tapFormat.sampleRate
    }

    // Scripture speech MUST come from micMixer (same path as working strip meters).
    // Preferring inputNode previously installed a silent/wrong-layout HW tap while
    // meters still moved — Listen heard nothing. inputNode is fallback only.
    speechFromInputNode = false
    micMixerTapInstalled = false

    // Mic strip meter + primary speech PCM — WHIP always comes from the master bus.
    var micErr: NSError?
    let micOk = SMCatchException(&micErr) { [self] in
      self.micMixer.installTap(onBus: 0, bufferSize: 1024, format: nil) {
        [weak self] buffer, _ in
        guard let self else { return }
        // Forward PCM before meter math — speech must work even if floatChannelData is nil.
        if self.speechListening || self.onMicBuffer != nil {
          self.speechRawTapCount += 1
          self.onMicBuffer?(buffer)
        }
        guard let ch = buffer.floatChannelData else { return }
        let frames = Int(buffer.frameLength)
        guard frames > 0 else { return }
        if buffer.format.sampleRate > 0 {
          self.engineSampleRate = buffer.format.sampleRate
        }
        let right = buffer.format.channelCount > 1 ? ch[1] : ch[0]
        var acc: Float = 0
        for f in 0..<frames {
          let l = ch[0][f]
          let r = right[f]
          acc += l * l + r * r
        }
        let rms = sqrt(acc / Float(max(frames * 2, 1)))
        if self.micMuted {
          self.micLevel = 0
          return
        }
        // Strip tap is pre-fader; scale for console meter.
        let next = self.meterLevel(
          rms: rms * max(self.micFader, 0) * max(self.masterFader, 0)
        )
        self.micLevel = max(self.micLevel * 0.5, next)
      }
      self.micMixerTapInstalled = true
    }

    var inputOk = false
    if !micOk {
      var inputErr: NSError?
      inputOk = SMCatchException(&inputErr) { [self] in
        let format = self.engine.inputNode.outputFormat(forBus: 0)
        guard format.sampleRate > 0, format.channelCount > 0 else { return }
        self.engine.inputNode.installTap(onBus: 0, bufferSize: 1024, format: format) {
          [weak self] buffer, _ in
          guard let self else { return }
          if self.speechListening || self.onMicBuffer != nil {
            self.speechRawTapCount += 1
            self.onMicBuffer?(buffer)
          }
          guard let ch = buffer.floatChannelData else { return }
          let frames = Int(buffer.frameLength)
          guard frames > 0 else { return }
          let right = buffer.format.channelCount > 1 ? ch[1] : ch[0]
          var acc: Float = 0
          for f in 0..<frames {
            let l = ch[0][f]
            let r = right[f]
            acc += l * l + r * r
          }
          let rms = sqrt(acc / Float(max(frames * 2, 1)))
          if self.micMuted {
            self.micLevel = 0
            return
          }
          let scaled = rms * max(self.micFader, 0) * max(self.masterFader, 0)
          self.micLevel = max(self.micLevel * 0.5, self.meterLevel(rms: scaled))
        }
        self.speechFromInputNode = true
      }
    }
    let micMeterOk = micOk || inputOk

    var playErr: NSError?
    let playOk = SMCatchException(&playErr) { [self] in
      self.playlistMixer.installTap(onBus: 0, bufferSize: 1024, format: nil) {
        [weak self] buffer, _ in
        guard let self, let ch = buffer.floatChannelData else { return }
        let frames = Int(buffer.frameLength)
        guard frames > 0 else { return }
        let right = buffer.format.channelCount > 1 ? ch[1] : ch[0]
        var acc: Float = 0
        for f in 0..<frames {
          let l = ch[0][f]
          let r = right[f]
          acc += l * l + r * r
        }
        let rms = sqrt(acc / Float(max(frames * 2, 1)))
        if self.playlistMuted {
          self.playlistLevel = 0
          return
        }
        self.playlistLevel = self.meterLevel(rms: rms * max(self.playlistFader, 0))
      }
    }

    // Master bus: meters + sole WHIP feed (resampled to 48 kHz in the ring).
    var masterErr: NSError?
    let masterOk = SMCatchException(&masterErr) { [self] in
      self.masterMixer.installTap(onBus: 0, bufferSize: 2048, format: nil) {
        [weak self] buffer, _ in
        guard let self, let ch = buffer.floatChannelData else { return }
        let frames = Int(buffer.frameLength)
        guard frames > 0 else { return }
        let right = buffer.format.channelCount > 1 ? ch[1] : ch[0]
        if buffer.format.sampleRate > 0 {
          self.engineSampleRate = buffer.format.sampleRate
        }
        var acc: Float = 0
        for f in 0..<frames {
          let l = ch[0][f]
          let r = right[f]
          acc += l * l + r * r
        }
        let rms = sqrt(acc / Float(max(frames * 2, 1)))
        let next = self.meterLevel(rms: rms * max(self.masterFader, 0))
        self.masterLevel = max(self.masterLevel * 0.5, next)

        // Always publish master (mic ± playlist). Matches console meters / Listen.
        self.writePcmToRing(
          left: ch[0],
          right: right,
          frames: frames,
          updateMaster: false,
          includesMaster: true
        )
      }
    }

    if !micMeterOk || !playOk || !masterOk {
      onStatus?("Mixer armed (some meters unavailable)")
    }
    // removeTap/installTap can leave the graph stopped on some macOS builds —
    // without a running engine, meters and scripture PCM stay at zero.
    if micWired && !engine.isRunning && !suspendedForSpeech {
      do {
        engine.prepare()
        try engine.start()
        NSLog("[scripture-speech] restarted engine after installing taps")
      } catch {
        NSLog(
          "[scripture-speech] engine start after taps failed: %@",
          error.localizedDescription
        )
      }
    }
    NSLog(
      "[scripture-speech] taps installed micMixer=%d inputNode=%d engineRunning=%d micWired=%d",
      micOk ? 1 : 0,
      inputOk ? 1 : 0,
      engine.isRunning ? 1 : 0,
      micWired ? 1 : 0
    )
  }

  /// Write engine-rate PCM into the 48 kHz WHIP ring (linear resample when needed).
  private func writePcmToRing(
    left: UnsafeMutablePointer<Float>,
    right: UnsafeMutablePointer<Float>,
    frames: Int,
    updateMaster: Bool,
    includesMaster: Bool
  ) {
    guard frames > 0 else { return }
    let srcRate = engineSampleRate > 0 ? engineSampleRate : publishSampleRate
    let needsResample = abs(srcRate - publishSampleRate) >= 1.0

    if !needsResample {
      appendStereoToRing(
        left: left,
        right: right,
        frames: frames,
        includesMaster: includesMaster
      )
    } else {
      // Convert device rate → 48 kHz at write time so WebRTC pulls 1:1.
      let ratio = publishSampleRate / srcRate
      let outFrames = max(1, Int((Double(frames) * ratio).rounded(.toNearestOrAwayFromZero)))
      let outL = UnsafeMutablePointer<Float>.allocate(capacity: outFrames)
      let outR = UnsafeMutablePointer<Float>.allocate(capacity: outFrames)
      defer {
        outL.deallocate()
        outR.deallocate()
      }
      for of in 0..<outFrames {
        let srcPos = Double(of) / ratio
        let i0 = min(Int(srcPos), frames - 1)
        let i1 = min(i0 + 1, frames - 1)
        let frac = Float(srcPos - Double(i0))
        let l0 = left[i0]
        let l1 = left[i1]
        let r0 = right[i0]
        let r1 = right[i1]
        outL[of] = l0 + (l1 - l0) * frac
        outR[of] = r0 + (r1 - r0) * frac
      }
      appendStereoToRing(
        left: outL,
        right: outR,
        frames: outFrames,
        includesMaster: includesMaster
      )
    }

    guard updateMaster else { return }
    var masterAcc: Float = 0
    for f in 0..<frames {
      let l = left[f]
      let r = right[f]
      masterAcc += l * l + r * r
    }
    let rms = sqrt(masterAcc / Float(max(frames * 2, 1)))
    let nextMaster = meterLevel(rms: rms)
    masterLevel = max(masterLevel * 0.5, nextMaster)
    if !micMuted, micFader > 0.01, nextMaster > micLevel {
      micLevel = max(micLevel, nextMaster)
    }
  }

  private func appendStereoToRing(
    left: UnsafeMutablePointer<Float>,
    right: UnsafeMutablePointer<Float>,
    frames: Int,
    includesMaster: Bool
  ) {
    bufferLock.lock()
    ringHasMasterGain = includesMaster
    for f in 0..<frames {
      let l = left[f]
      let r = right[f]
      for sample in [l, r] {
        if ringCount < ringCapacity {
          ring[ringWrite] = sample
          ringWrite = (ringWrite + 1) % ringCapacity
          ringCount += 1
        } else {
          ring[ringWrite] = sample
          ringWrite = (ringWrite + 1) % ringCapacity
          ringRead = (ringRead + 1) % ringCapacity
        }
      }
    }
    bufferLock.unlock()
  }

  /// Apple: check input node's HW format for non-zero sample rate + channel count.
  /// After device switches this can briefly read as zero — poll briefly.
  private func waitForValidInputFormat(attempts: Int = 12) -> AVAudioFormat? {
    engine.prepare()
    for _ in 0..<attempts {
      let input = engine.inputNode
      let hw = input.inputFormat(forBus: 0)
      if hw.sampleRate > 0, hw.channelCount > 0 {
        return hw
      }
      let out = input.outputFormat(forBus: 0)
      if out.sampleRate > 0, out.channelCount > 0 {
        return out
      }
      usleep(25_000)
    }
    return nil
  }

  /// Last-resort: make macOS route the chosen mic into AVAudioEngine.
  /// Prefer `setEngineInputDevice` — rewriting the system default breaks other apps
  /// (e.g. browser Studio publishing a different channel on the same Mac).
  private func setSystemDefaultInput(_ deviceID: AudioDeviceID) throws {
    guard deviceID != 0 else { return }
    let current = defaultInputDeviceID()
    if current == deviceID { return }

    if previousSystemDefaultInput == nil, current != 0 {
      previousSystemDefaultInput = current
    }

    var device = deviceID
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDefaultInputDevice,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    let status = AudioObjectSetPropertyData(
      AudioObjectID(kAudioObjectSystemObject),
      &address,
      0,
      nil,
      UInt32(MemoryLayout<AudioDeviceID>.stride),
      &device
    )
    guard status == noErr else {
      throw NSError(
        domain: "NativeAudio",
        code: Int(status),
        userInfo: [
          NSLocalizedDescriptionKey:
            "Failed to set system input device (\(status)).",
        ]
      )
    }
  }

  private func restoreSystemDefaultInputIfNeeded() {
    guard let previous = previousSystemDefaultInput, previous != 0 else { return }
    previousSystemDefaultInput = nil
    var device = previous
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDefaultInputDevice,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    _ = AudioObjectSetPropertyData(
      AudioObjectID(kAudioObjectSystemObject),
      &address,
      0,
      nil,
      UInt32(MemoryLayout<AudioDeviceID>.stride),
      &device
    )
  }

  private func setEngineInputDevice(_ deviceID: AudioDeviceID) throws {
    try setCurrentDevice(deviceID, on: engine.inputNode.audioUnit, label: "input")
  }

  private func setEngineOutputDevice(_ deviceID: AudioDeviceID) throws {
    try setCurrentDevice(deviceID, on: engine.outputNode.audioUnit, label: "output")
  }

  private func setCurrentDevice(
    _ deviceID: AudioDeviceID,
    on audioUnit: AudioUnit?,
    label: String
  ) throws {
    guard deviceID != 0 else { return }
    engine.prepare()
    guard let audioUnit else {
      throw NSError(
        domain: "NativeAudio",
        code: 15,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Audio \(label) unit not ready. Enable the microphone and try again.",
        ]
      )
    }

    var current = AudioDeviceID(0)
    var size = UInt32(MemoryLayout<AudioDeviceID>.stride)
    let getStatus = AudioUnitGetProperty(
      audioUnit,
      kAudioOutputUnitProperty_CurrentDevice,
      kAudioUnitScope_Global,
      0,
      &current,
      &size
    )
    if getStatus == noErr, current == deviceID {
      return
    }

    var device = deviceID
    let status = AudioUnitSetProperty(
      audioUnit,
      kAudioOutputUnitProperty_CurrentDevice,
      kAudioUnitScope_Global,
      0,
      &device,
      UInt32(MemoryLayout<AudioDeviceID>.stride)
    )
    if status == noErr {
      return
    }
    throw NSError(
      domain: "NativeAudio",
      code: Int(status),
      userInfo: [
        NSLocalizedDescriptionKey:
          "Failed to select \(label) device (\(status)).",
      ]
    )
  }

  private func defaultInputDeviceID() -> AudioDeviceID {
    var deviceID = AudioDeviceID(0)
    var size = UInt32(MemoryLayout<AudioDeviceID>.stride)
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDefaultInputDevice,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    _ = AudioObjectGetPropertyData(
      AudioObjectID(kAudioObjectSystemObject),
      &address,
      0,
      nil,
      &size,
      &deviceID
    )
    return deviceID
  }

  private func defaultOutputDeviceID() -> AudioDeviceID {
    var deviceID = AudioDeviceID(0)
    var size = UInt32(MemoryLayout<AudioDeviceID>.stride)
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioHardwarePropertyDefaultOutputDevice,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    _ = AudioObjectGetPropertyData(
      AudioObjectID(kAudioObjectSystemObject),
      &address,
      0,
      nil,
      &size,
      &deviceID
    )
    return deviceID
  }

  private func deviceTransportType(_ id: AudioDeviceID) -> UInt32 {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioDevicePropertyTransportType,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    var transport: UInt32 = 0
    var size = UInt32(MemoryLayout<UInt32>.stride)
    guard AudioObjectGetPropertyData(id, &address, 0, nil, &size, &transport) == noErr else {
      return 0
    }
    return transport
  }

  private func deviceIsAlive(_ id: AudioDeviceID) -> Bool {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioDevicePropertyDeviceIsAlive,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    var alive: UInt32 = 0
    var size = UInt32(MemoryLayout<UInt32>.stride)
    guard AudioObjectGetPropertyData(id, &address, 0, nil, &size, &alive) == noErr else {
      return true
    }
    return alive != 0
  }

  private func deviceUID(_ id: AudioDeviceID) -> String? {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioDevicePropertyDeviceUID,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    var uid: CFString = "" as CFString
    var size = UInt32(MemoryLayout<CFString>.stride)
    let err = withUnsafeMutablePointer(to: &uid) { ptr in
      AudioObjectGetPropertyData(id, &address, 0, nil, &size, ptr)
    }
    guard err == noErr else { return nil }
    return uid as String
  }

  private func outputChannelCount(_ id: AudioDeviceID) -> Int {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioDevicePropertyStreamConfiguration,
      mScope: kAudioDevicePropertyScopeOutput,
      mElement: kAudioObjectPropertyElementMain
    )
    var size: UInt32 = 0
    guard AudioObjectGetPropertyDataSize(id, &address, 0, nil, &size) == noErr, size > 0 else {
      return 0
    }
    let raw = UnsafeMutableRawPointer.allocate(
      byteCount: Int(size),
      alignment: MemoryLayout<AudioBufferList>.alignment
    )
    defer { raw.deallocate() }
    guard AudioObjectGetPropertyData(id, &address, 0, nil, &size, raw) == noErr else {
      return 0
    }
    let abl = raw.assumingMemoryBound(to: AudioBufferList.self)
    let buffers = UnsafeMutableAudioBufferListPointer(abl)
    var channels = 0
    for buffer in buffers {
      channels += Int(buffer.mNumberChannels)
    }
    return channels
  }

  private func inputChannelCount(_ id: AudioDeviceID) -> Int {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioDevicePropertyStreamConfiguration,
      mScope: kAudioDevicePropertyScopeInput,
      mElement: kAudioObjectPropertyElementMain
    )
    var size: UInt32 = 0
    guard AudioObjectGetPropertyDataSize(id, &address, 0, nil, &size) == noErr, size > 0 else {
      return 0
    }
    let raw = UnsafeMutableRawPointer.allocate(byteCount: Int(size), alignment: MemoryLayout<AudioBufferList>.alignment)
    defer { raw.deallocate() }
    guard AudioObjectGetPropertyData(id, &address, 0, nil, &size, raw) == noErr else {
      return 0
    }
    let abl = raw.assumingMemoryBound(to: AudioBufferList.self)
    let buffers = UnsafeMutableAudioBufferListPointer(abl)
    var channels = 0
    for buffer in buffers {
      channels += Int(buffer.mNumberChannels)
    }
    return channels
  }

  private func deviceName(_ id: AudioDeviceID) -> String? {
    var address = AudioObjectPropertyAddress(
      mSelector: kAudioObjectPropertyName,
      mScope: kAudioObjectPropertyScopeGlobal,
      mElement: kAudioObjectPropertyElementMain
    )
    var name: CFString = "" as CFString
    var size = UInt32(MemoryLayout<CFString>.stride)
    let err = withUnsafeMutablePointer(to: &name) { ptr in
      AudioObjectGetPropertyData(id, &address, 0, nil, &size, ptr)
    }
    guard err == noErr else {
      // Fallback to legacy device name key.
      var legacy = AudioObjectPropertyAddress(
        mSelector: kAudioDevicePropertyDeviceNameCFString,
        mScope: kAudioObjectPropertyScopeGlobal,
        mElement: kAudioObjectPropertyElementMain
      )
      var legacyName: CFString = "" as CFString
      var legacySize = UInt32(MemoryLayout<CFString>.stride)
      let legacyErr = withUnsafeMutablePointer(to: &legacyName) { ptr in
        AudioObjectGetPropertyData(id, &legacy, 0, nil, &legacySize, ptr)
      }
      guard legacyErr == noErr else { return nil }
      return legacyName as String
    }
    return name as String
  }

  private func applyGains() {
    // Strips stay at unity; mute×fader live on program sends so cue can stay pre-mute.
    micMixer.outputVolume = 1
    playlistMixer.outputVolume = 1
    micProgramSend.outputVolume = micMuted ? 0 : max(0, micFader)
    playlistProgramSend.outputVolume = playlistMuted ? 0 : max(0, playlistFader)
    masterMixer.outputVolume = 1

    let publish = micMuted ? Float(0) : max(0, micFader) * max(0, masterFader)
    bufferLock.lock()
    cachedPublishGain = publish
    bufferLock.unlock()

    guard nodesAttached else { return }

    let cueActive = micCue || playlistCue
    // Pre-fader / pre-mute PFL — hear the strip even when muted for air.
    micCueSend.outputVolume = micCue ? 1 : 0
    playlistCueSend.outputVolume = playlistCue ? 1 : 0
    cueMixer.outputVolume = 1
    // Duck program in HP while cueing. When cue is off, keep a tiny programMonitor
    // gain so the master→HP edge stays connected, but put the real pull on mainMixer
    // (see headphonesOutputVolume) — otherwise meter/speech taps starve when armed.
    programMonitor.outputVolume = cueActive ? 0 : 0.001
    // Keep the mic strip pulling even when muted + CUE off so taps stay live.
    micTapPull.outputVolume = (micWired || speechListening) ? 0.001 : 0
    // Cue level is independent of the master fader (operator monitoring).
    engine.mainMixerNode.outputVolume = headphonesOutputVolume
  }

  private func startMeters() {
    meterTimer?.invalidate()
    meterTimer = Timer.scheduledTimer(withTimeInterval: 1.0 / 30.0, repeats: true) { [weak self] _ in
      guard let self else { return }
      self.refreshTrackTimes()
      // Ballistics: fall smoothly when the tap is quiet between buffers.
      self.micLevel *= 0.9
      self.playlistLevel *= 0.9
      self.masterLevel *= 0.92
      if self.micLevel < 0.008 { self.micLevel = 0 }
      if self.playlistLevel < 0.008 { self.playlistLevel = 0 }
      if self.masterLevel < 0.008 { self.masterLevel = 0 }
      self.onMeters?(self.micLevel, self.playlistLevel, self.masterLevel)
    }
  }

  private func refreshTrackTimes() {
    var changed = false
    for (id, player) in playerNodes {
      guard trackMeta[id] != nil else { continue }
      if player.isPlaying {
        // AVAudioPlayerNode doesn't expose easy position; keep playing flag.
        if trackMeta[id]?.playing != true {
          trackMeta[id]?.playing = true
          changed = true
        }
      }
    }
    if changed { emitTracks() }
  }

  private func emitTracks() {
    onTracks?(Array(trackMeta.values))
  }

  private func resolveAudioURL(_ urlString: String, trackId: String) throws -> URL {
    if urlString.hasPrefix("http://") || urlString.hasPrefix("https://") {
      guard let remote = URL(string: urlString) else {
        throw NSError(domain: "NativeAudio", code: 2, userInfo: [NSLocalizedDescriptionKey: "Bad track URL"])
      }
      let data = try Data(contentsOf: remote)
      let ext = remote.pathExtension.isEmpty ? "m4a" : remote.pathExtension
      let dest = FileManager.default.temporaryDirectory
        .appendingPathComponent("sm-track-\(trackId).\(ext)")
      try data.write(to: dest, options: .atomic)
      tempFiles[trackId] = dest
      return dest
    }
    return URL(fileURLWithPath: urlString)
  }
}

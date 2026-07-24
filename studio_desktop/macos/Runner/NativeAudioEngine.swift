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
  private let cueMixer = AVAudioMixerNode()

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
  /// Last time the master tap fed the WHIP ring (fallback uses mic tap).
  private var lastMasterRingWrite = Date.distantPast
  /// Ring PCM is a full wet master mix (all faders baked) vs dry mic.
  private var ringHasMasterGain = false
  /// Mute × mic × master — read from the WebRTC audio callback.
  private var cachedPublishGain: Float = 1
  private var mixSampleRate: Double = 48_000
  /// System default input before Studio last changed it — restored on tearDown so
  /// opening Desktop does not permanently steal the mic from another Studio on this Mac.
  private var previousSystemDefaultInput: AudioDeviceID?

  var onMeters: ((Float, Float, Float) -> Void)?
  var onTracks: (([TrackState]) -> Void)?
  var onDevices: (([InputDevice], String?) -> Void)?
  var onOutputs: (([InputDevice], String?) -> Void)?
  var onStatus: ((String) -> Void)?

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
    onStatus?("Mic armed — native mixer ready")
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
      return
    }

    do {
      try rebuildAndStart(deviceId: deviceId)
      armed = true
      let devices = listInputDevices()
      let label = devices.first(where: { $0.deviceId == deviceId })?.label ?? deviceId
      onDevices?(devices, selectedDeviceId)
      onOutputs?(listOutputDevices(), selectedOutputDeviceId)
      onStatus?("Input: \(label)")
    } catch {
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
  }

  func clearMixRing() {
    bufferLock.lock()
    ringRead = 0
    ringWrite = 0
    ringCount = 0
    ringHasMasterGain = false
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
    engine.attach(player)
    // Only wire into playlist bus once the mix graph exists (after arm).
    if micWired {
      engine.connect(player, to: playlistMixer, format: file.processingFormat)
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

  var currentMixSampleRate: Double { mixSampleRate }

  /// Pull mix audio for WebRTC. Ring is stereo interleaved; downmixes to `channels`.
  /// Returns frames actually filled. Does **not** zero the destination on underrun
  /// (caller should leave the WebRTC ADM buffer untouched if 0).
  @discardableResult
  func readMixInterleaved(frames: Int, channels: Int, into dest: UnsafeMutablePointer<Float>) -> Int {
    bufferLock.lock()
    defer { bufferLock.unlock() }
    return readMixInterleavedUnlocked(frames: frames, channels: channels, into: dest)
  }

  /// Like `readMixInterleaved`, but linear-resamples from the AVAudioEngine rate to
  /// WebRTC's process rate (often 48 kHz) so 44.1 kHz devices don't pitch-shift.
  @discardableResult
  func readMixResampled(
    frames: Int,
    channels: Int,
    targetSampleRate: Double,
    into dest: UnsafeMutablePointer<Float>
  ) -> Int {
    bufferLock.lock()
    defer { bufferLock.unlock() }

    let srcRate = mixSampleRate > 0 ? mixSampleRate : targetSampleRate
    let outCh = max(channels, 1)
    guard frames > 0, targetSampleRate > 0 else { return 0 }

    // Same rate — fast path.
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
    engine.attach(micMixer)
    engine.attach(playlistMixer)
    engine.attach(masterMixer)
    engine.attach(cueMixer)
    nodesAttached = true
  }

  /// Drop the engine and reattach nodes — clears stuck AU "!dev" / -10867 state.
  private func resetEngineInstance() {
    removeAllTapsSafely()
    if engine.isRunning {
      engine.stop()
    }
    engine = AVAudioEngine()
    nodesAttached = false
    attachNodesIfNeeded()
    for (id, player) in playerNodes {
      engine.attach(player)
      if let file = playerFiles[id] {
        engine.connect(player, to: playlistMixer, format: file.processingFormat)
      }
    }
  }

  /// Minimal graph: input → mic → master → output (+ playlist → master).
  /// Capture via tap after start. No sink / fan-out (those caused !dev and -10867).
  private func rebuildAndStart(deviceId: String?) throws {
    do {
      try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: true)
      return
    } catch {
      onStatus?("Resetting audio engine…")
      resetEngineInstance()
    }
    try rebuildAndStartOnce(deviceId: deviceId, allowDeviceBind: false)
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
    engine.disconnectNodeOutput(engine.inputNode)
    engine.disconnectNodeOutput(micMixer)
    engine.disconnectNodeOutput(playlistMixer)
    engine.disconnectNodeOutput(masterMixer)
    engine.disconnectNodeOutput(cueMixer)
    for player in playerNodes.values {
      engine.disconnectNodeOutput(player)
    }

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

    let activeInput = engine.inputNode
    var err: NSError?
    let wired = SMCatchException(&err) { [self] in
      // Single linear chain — converters inserted automatically toward hardware out.
      // nil format lets the engine match the input node's HW format (avoids HW mismatch).
      self.engine.connect(activeInput, to: self.micMixer, format: nil)
      self.engine.connect(self.micMixer, to: self.masterMixer, format: micFormat)
      self.engine.connect(self.playlistMixer, to: self.masterMixer, format: micFormat)
      // nil into mainMixer lets the engine match hardware output rate.
      self.engine.connect(self.masterMixer, to: self.engine.mainMixerNode, format: nil)
      for (id, player) in self.playerNodes {
        if let file = self.playerFiles[id] {
          self.engine.connect(player, to: self.playlistMixer, format: file.processingFormat)
        }
      }
    }
    guard wired else {
      let message = err?.localizedDescription ?? ""
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
      throw err ?? NSError(
        domain: "NativeAudio",
        code: 11,
        userInfo: [
          NSLocalizedDescriptionKey:
            "Could not build mixer graph. Try System Default Microphone.",
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
            "Could not start audio engine (\(error.localizedDescription)). Check mic permission and try again.",
        ]
      )
    }

    installMeterAndCaptureTaps()
  }

  /// Volume 0 stops the graph from pulling — taps go silent and WHIP gets no mic.
  /// Keep a small pull when cue is off; full volume when cue monitoring.
  private var monitorPullVolume: Float {
    if micCue || playlistCue { return 1.0 }
    // Plain HAL needs a real pull so input taps see PCM (meters + WHIP).
    return 0.35
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
    removeAllTapsSafely()
    let tapFormat = engine.inputNode.outputFormat(forBus: 0)
    if tapFormat.sampleRate > 0 {
      mixSampleRate = tapFormat.sampleRate
    }

    // Prefer micMixer (nil format): more reliable than tapping inputNode with an
    // explicit format, which can install successfully but deliver silence.
    var micErr: NSError?
    let micOk = SMCatchException(&micErr) { [self] in
      self.micMixer.installTap(onBus: 0, bufferSize: 1024, format: nil) {
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
        if self.micMuted {
          self.micLevel = 0
          return
        }
        // Ignore near-silence so we don't fill the WHIP ring with zeros and
        // wipe the live ADM mic when the engine briefly loses the device.
        guard rms > 0.0003 else { return }
        // Post-mic-fader; include master so the strip matches Listen.
        let next = self.meterLevel(rms: rms * max(self.masterFader, 0))
        self.micLevel = max(self.micLevel * 0.5, next)
        if Date().timeIntervalSince(self.lastMasterRingWrite) > 0.05 {
          // micMixer output is post-fader — undo so the ring stays dry and
          // MixCaptureInjector can apply the live Mic×Master gain.
          self.writeDryMicToRing(left: ch[0], right: right, frames: frames)
        }
      }
    }

    // Fallback: input node when micMixer tap is unavailable (already dry).
    var inputErr: NSError?
    var inputOk = false
    if !micOk {
      inputOk = SMCatchException(&inputErr) { [self] in
        let format = self.engine.inputNode.outputFormat(forBus: 0)
        guard format.sampleRate > 0, format.channelCount > 0 else { return }
        self.engine.inputNode.installTap(onBus: 0, bufferSize: 1024, format: format) {
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
          if self.micMuted {
            self.micLevel = 0
            return
          }
          guard rms > 0.0003 else { return }
          // Dry input — apply console gain so the meter follows the faders.
          let scaled = rms * max(self.micFader, 0) * max(self.masterFader, 0)
          let next = self.meterLevel(rms: scaled)
          self.micLevel = max(self.micLevel * 0.5, next)
          if Date().timeIntervalSince(self.lastMasterRingWrite) > 0.05 {
            self.writePcmToRing(
              left: ch[0],
              right: right,
              frames: frames,
              updateMaster: false,
              includesMaster: false
            )
          }
        }
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
        self.playlistLevel = self.meterLevel(rms: rms)
      }
    }

    // Master bus: meters. When playlist is audible, also feed WHIP with the full mix.
    var masterErr: NSError?
    let masterOk = SMCatchException(&masterErr) { [self] in
      self.masterMixer.installTap(onBus: 0, bufferSize: 4096, format: nil) {
        [weak self] buffer, _ in
        guard let self, let ch = buffer.floatChannelData else { return }
        let frames = Int(buffer.frameLength)
        guard frames > 0 else { return }
        let right = buffer.format.channelCount > 1 ? ch[1] : ch[0]
        if buffer.format.sampleRate > 0 {
          self.mixSampleRate = buffer.format.sampleRate
        }
        var acc: Float = 0
        for f in 0..<frames {
          let l = ch[0][f]
          let r = right[f]
          acc += l * l + r * r
        }
        let rms = sqrt(acc / Float(max(frames * 2, 1)))
        // masterMixer is unity; include master fader for the strip / header meter.
        let next = self.meterLevel(rms: rms * max(self.masterFader, 0))
        self.masterLevel = max(self.masterLevel * 0.5, next)

        // Playlist present → publish master (mic+playlist). Mic-only uplink is
        // handled by the mic tap to avoid double-writing / double-speed audio.
        let playlistHot = !self.playlistMuted && self.playlistFader > 0.02
        if playlistHot, rms > 0.0003 {
          self.lastMasterRingWrite = Date()
          self.writePcmToRing(
            left: ch[0],
            right: right,
            frames: frames,
            updateMaster: false,
            includesMaster: true
          )
        }
      }
    }

    if !micMeterOk || !playOk || !masterOk {
      onStatus?("Mixer armed (some meters unavailable)")
    }
  }

  /// Undo micMixer.outputVolume so the WHIP ring stores dry mic PCM.
  private func writeDryMicToRing(
    left: UnsafeMutablePointer<Float>,
    right: UnsafeMutablePointer<Float>,
    frames: Int
  ) {
    let baked = max(micFader, 0.0001)
    let inv = 1 / baked
    let dryL = UnsafeMutablePointer<Float>.allocate(capacity: frames)
    let dryR = UnsafeMutablePointer<Float>.allocate(capacity: frames)
    defer {
      dryL.deallocate()
      dryR.deallocate()
    }
    for f in 0..<frames {
      dryL[f] = left[f] * inv
      dryR[f] = right[f] * inv
    }
    writePcmToRing(
      left: dryL,
      right: dryR,
      frames: frames,
      updateMaster: false,
      includesMaster: false
    )
  }

  private func writePcmToRing(
    left: UnsafeMutablePointer<Float>,
    right: UnsafeMutablePointer<Float>,
    frames: Int,
    updateMaster: Bool,
    includesMaster: Bool
  ) {
    guard frames > 0 else { return }
    var masterAcc: Float = 0
    bufferLock.lock()
    ringHasMasterGain = includesMaster
    for f in 0..<frames {
      let l = left[f]
      let r = right[f]
      masterAcc += l * l + r * r
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
    guard updateMaster else { return }
    let rms = sqrt(masterAcc / Float(max(frames * 2, 1)))
    let nextMaster = meterLevel(rms: rms)
    masterLevel = max(masterLevel * 0.5, nextMaster)
    if !micMuted, micFader > 0.01, nextMaster > micLevel {
      micLevel = max(micLevel, nextMaster)
    }
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
    // Graph carries mic/playlist faders for local monitor + playlist mix.
    // Mic tap undoes mic fader when writing the WHIP ring (dry PCM).
    // MixCaptureInjector always applies cachedPublishGain (or master-only on wet mix).
    micMixer.outputVolume = micMuted ? 0 : max(0, micFader)
    playlistMixer.outputVolume = playlistMuted ? 0 : max(0, playlistFader)
    masterMixer.outputVolume = 1

    let publish = micMuted ? Float(0) : max(0, micFader) * max(0, masterFader)
    bufferLock.lock()
    cachedPublishGain = publish
    bufferLock.unlock()

    if nodesAttached {
      engine.mainMixerNode.outputVolume =
        monitorPullVolume * max(0, masterFader)
    }
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

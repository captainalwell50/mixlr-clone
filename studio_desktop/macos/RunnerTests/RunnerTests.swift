import AVFoundation
import Cocoa
import FlutterMacOS
import XCTest

@testable import Sound_Mix_Live_Studio

/// Regression coverage for live mic hot-swap continuity (publish must not stick at silence).
class RunnerTests: XCTestCase {

  func testPublisherReconnectYieldsOnlyWhenAnotherClaimIsActive() {
    XCTAssertEqual(
      PublisherReconnectDecision.decide(owns: true, replaced: false, checkFailed: false),
      "recover"
    )
    XCTAssertEqual(
      PublisherReconnectDecision.decide(owns: false, replaced: true, checkFailed: false),
      "replaced"
    )
    XCTAssertEqual(
      PublisherReconnectDecision.decide(owns: false, replaced: true, checkFailed: true),
      "recover"
    )
  }

  func testWhipIceDisconnectIsNotImmediateFailure() {
    XCTAssertEqual(
      WhipIcePolicy.action(ice: "disconnected", isCurrentConnection: true, stopping: false),
      .ignore
    )
    XCTAssertEqual(
      WhipIcePolicy.action(ice: "failed", isCurrentConnection: true, stopping: false),
      .reconnect
    )
    XCTAssertEqual(
      WhipIcePolicy.action(ice: "failed", isCurrentConnection: false, stopping: false),
      .ignore
    )
    XCTAssertEqual(
      WhipIcePolicy.action(ice: "connected", isCurrentConnection: true, stopping: false),
      .connected
    )
  }

  func testPublishContinuityHoldStoresAndFills() {
    let hold = PublishContinuityHold()
    XCTAssertFalse(hold.hasSamples)

    var src: [Float] = [0.5, -0.25, 0.125, -0.5]
    src.withUnsafeMutableBufferPointer { buf in
      hold.store(interleaved: buf.baseAddress!, frames: 2, channels: 2)
    }
    XCTAssertTrue(hold.hasSamples)

    var dest = [Float](repeating: 0, count: 8)
    dest.withUnsafeMutableBufferPointer { buf in
      XCTAssertTrue(hold.fill(frames: 4, channels: 2, into: buf.baseAddress!))
    }
    // First two frames match stored stereo; later frames hold the last frame.
    XCTAssertEqual(dest[0], 0.5, accuracy: 0.0001)
    XCTAssertEqual(dest[1], -0.25, accuracy: 0.0001)
    XCTAssertEqual(dest[2], 0.125, accuracy: 0.0001)
    XCTAssertEqual(dest[3], -0.5, accuracy: 0.0001)
    XCTAssertEqual(dest[4], 0.125, accuracy: 0.0001)
    XCTAssertEqual(dest[5], -0.5, accuracy: 0.0001)
  }

  func testPublishContinuityHoldForcedFlag() {
    let hold = PublishContinuityHold()
    XCTAssertFalse(hold.forced)
    hold.beginHold()
    XCTAssertTrue(hold.forced)
    hold.endHold()
    XCTAssertFalse(hold.forced)
  }

  func testPublishContinuityHoldEmptyFillFails() {
    let hold = PublishContinuityHold()
    var dest = [Float](repeating: 1, count: 4)
    dest.withUnsafeMutableBufferPointer { buf in
      XCTAssertFalse(hold.fill(frames: 2, channels: 2, into: buf.baseAddress!))
    }
  }

  func testEnsurePublishingGraphIsSafeBeforeArm() {
    let engine = NativeAudioEngine()
    engine.ensurePublishingGraph()
    XCTAssertFalse(engine.isSuspendedForSpeech)
  }

  func testLocalPlaylistMonitorGainIsCueGated() {
    XCTAssertEqual(
      NativeAudioEngine.localPlaylistMonitorGain(muted: false, cue: false, fader: 1),
      0,
      accuracy: 0.0001
    )
    XCTAssertEqual(
      NativeAudioEngine.localPlaylistMonitorGain(muted: true, cue: true, fader: 1),
      0,
      accuracy: 0.0001
    )
    XCTAssertEqual(
      NativeAudioEngine.localPlaylistMonitorGain(muted: false, cue: true, fader: 0.5),
      0.5,
      accuracy: 0.0001
    )
  }

  func testPlaylistPlaybackFormatIsCanonicalStereo48k() {
    let format = NativeAudioEngine.playlistPlaybackFormat
    XCTAssertEqual(format.sampleRate, 48_000, accuracy: 0.1)
    XCTAssertEqual(format.channelCount, 2)
    XCTAssertFalse(format.isInterleaved)
    XCTAssertTrue(NativeAudioEngine.formatsMatch(format, format))
  }

  func testPlaybackCacheNameIncludesLayout() {
    let format = NativeAudioEngine.playlistPlaybackFormat
    let name = NativeAudioEngine.playbackCacheFileName(id: "asset-9", format: format)
    XCTAssertTrue(name.contains("asset-9"))
    XCTAssertTrue(name.contains("48000"))
    XCTAssertTrue(name.contains("-2-"))
    XCTAssertTrue(name.hasSuffix(".caf"))
    XCTAssertNotEqual(
      name,
      NativeAudioEngine.playbackCacheFileName(id: "other", format: format)
    )
    XCTAssertNotEqual(
      name,
      NativeAudioEngine.playbackCacheFileName(
        id: "asset-9",
        format: AVAudioFormat(standardFormatWithSampleRate: 44_100, channels: 2)!
      )
    )
  }

  func testMixLayoutOverrideControlsPublishStereo() {
    XCTAssertEqual(MixLayout.parse(nil), .auto)
    XCTAssertEqual(MixLayout.parse("MONO"), .mono)
    XCTAssertEqual(MixLayout.parse("stereo"), .stereo)
    let engine = NativeAudioEngine()
    XCTAssertEqual(engine.mixLayout, .auto)
    engine.setMixLayout("auto")
    XCTAssertTrue(engine.shouldPublishStereo())
    engine.setMixLayout("mono")
    XCTAssertFalse(engine.shouldPublishStereo())
    engine.setMixLayout("stereo")
    XCTAssertTrue(engine.shouldPublishStereo())
  }

  func testAutoPublishesStereoWhenMicVotesAreZero() {
    let engine = NativeAudioEngine()
    engine.setMixLayout("auto")
    // receivedStereoVotes starts at 0 (mono microphone). Auto must still
    // keep a stereo-capable programme so a stereo playlist is not downmixed.
    XCTAssertTrue(engine.shouldPublishStereo())
    engine.setMixLayout("mono")
    XCTAssertFalse(engine.shouldPublishStereo())
    engine.setMixLayout("stereo")
    XCTAssertTrue(engine.shouldPublishStereo())
  }

  func testPreferHighQualityOpusMatchesReceivedChannels() {
    let sdp = [
      "v=0",
      "m=audio 9 UDP/TLS/RTP/SAVPF 111",
      "a=rtpmap:111 opus/48000/2",
      "a=fmtp:111 minptime=10;useinbandfec=1",
      "",
    ].joined(separator: "\n")
    let mono = NativeWhipPublisher.preferHighQualityOpus(sdp, stereo: false)
    XCTAssertTrue(mono.contains("stereo=0"))
    XCTAssertTrue(mono.contains("sprop-stereo=0"))
    let stereo = NativeWhipPublisher.preferHighQualityOpus(sdp, stereo: true)
    XCTAssertTrue(stereo.contains("stereo=1"))
    XCTAssertTrue(stereo.contains("sprop-stereo=1"))
    XCTAssertTrue(stereo.contains("maxaveragebitrate=320000"))
    XCTAssertTrue(stereo.contains("stereo=1"))
    let custom = NativeWhipPublisher.preferHighQualityOpus(sdp, stereo: false, bitrate: 256_000)
    XCTAssertTrue(custom.contains("stereo=0"))
    XCTAssertTrue(custom.contains("maxaveragebitrate=256000"))
  }

  func testPreferHighQualityOpusStripsG722() {
    let sdp = [
      "v=0",
      "m=audio 9 UDP/TLS/RTP/SAVPF 9 111 0 8",
      "a=rtpmap:9 G722/8000",
      "a=rtpmap:111 opus/48000/2",
      "a=fmtp:111 minptime=10;useinbandfec=1",
      "a=rtpmap:0 PCMU/8000",
      "a=rtpmap:8 PCMA/8000",
      "",
    ].joined(separator: "\n")
    let out = NativeWhipPublisher.preferHighQualityOpus(sdp)
    XCTAssertTrue(out.contains("m=audio 9 UDP/TLS/RTP/SAVPF 111"))
    XCTAssertFalse(out.lowercased().contains("g722"))
    XCTAssertFalse(out.contains("PCMU"))
    XCTAssertTrue(out.contains("opus/48000"))
  }

  func testSoftwarePlaylistMixesAudibleSamples() throws {
    let url = try Self.writeSineCAF()
    defer { try? FileManager.default.removeItem(at: url) }
    let file = try AVAudioFile(forReading: url)
    let voice = PlaylistVoice()
    try voice.load(file: file)
    voice.play()
    var left = [Float](repeating: 0, count: 1024)
    var right = [Float](repeating: 0, count: 1024)
    var rms: Float = 0
    left.withUnsafeMutableBufferPointer { l in
      right.withUnsafeMutableBufferPointer { r in
        rms = voice.mix(
          left: l.baseAddress!,
          right: r.baseAddress!,
          frames: 1024,
          destSampleRate: 48_000,
          gain: 1
        )
      }
    }
    XCTAssertGreaterThan(rms, 0.01)
    XCTAssertTrue(left.contains { abs($0) > 0.02 })
  }

  func testQueueAndPlayDoesNotThrow() throws {
    let engine = NativeAudioEngine()
    try engine.start()
    let url = try Self.writeSineCAF()
    defer {
      engine.tearDown()
      try? FileManager.default.removeItem(at: url)
    }
    let queued = expectation(description: "queue")
    engine.queueTrack(id: "t1", title: "sine", url: url.path, assetId: nil) { error, path in
      XCTAssertNil(error)
      XCTAssertNotNil(path)
      queued.fulfill()
    }
    wait(for: [queued], timeout: 8)
    let played = expectation(description: "play")
    engine.play("t1") { error in
      XCTAssertNil(error)
      played.fulfill()
    }
    wait(for: [played], timeout: 8)
    XCTAssertTrue(engine.playlistVoicePlaying("t1"))
    let samples = engine.debugMixPlaylist(frames: 2048, sampleRate: 48_000)
    XCTAssertTrue(
      samples.contains { abs($0) > 0.01 },
      "Play must mix audible samples without using the live mic graph"
    )
  }

  func testPlayWhilePreviewEngineRunning() throws {
    let engine = NativeAudioEngine()
    try engine.start()
    let url = try Self.writeSineCAF()
    defer {
      engine.tearDown()
      try? FileManager.default.removeItem(at: url)
    }
    try engine.startPreviewGraphForTest()
    XCTAssertTrue(engine.isAudioEngineRunning)
    let queued = expectation(description: "queue")
    engine.queueTrack(id: "t2", title: "sine-live", url: url.path, assetId: nil) { error, _ in
      XCTAssertNil(error)
      queued.fulfill()
    }
    wait(for: [queued], timeout: 8)
    let played = expectation(description: "play")
    engine.play("t2") { error in
      XCTAssertNil(error)
      played.fulfill()
    }
    wait(for: [played], timeout: 8)
    XCTAssertTrue(engine.isAudioEngineRunning)
    XCTAssertTrue(engine.playlistVoicePlaying("t2"))
    let samples = engine.debugMixPlaylist(frames: 2048, sampleRate: 48_000)
    XCTAssertTrue(samples.contains { abs($0) > 0.01 })
  }

  func testPlayWhileMicArmed() throws {
    let engine = NativeAudioEngine()
    try engine.start()
    let url = try Self.writeSineCAF()
    defer {
      engine.tearDown()
      try? FileManager.default.removeItem(at: url)
    }
    do {
      try engine.armMic(deviceId: nil)
    } catch {
      throw XCTSkip("Mic not available for live play test: \(error)")
    }
    let queued = expectation(description: "queue")
    engine.queueTrack(id: "t3", title: "sine-armed", url: url.path, assetId: nil) { error, _ in
      XCTAssertNil(error)
      queued.fulfill()
    }
    wait(for: [queued], timeout: 8)
    let played = expectation(description: "play")
    engine.play("t3") { error in
      XCTAssertNil(error)
      played.fulfill()
    }
    wait(for: [played], timeout: 8)
    XCTAssertTrue(engine.playlistVoicePlaying("t3"))
    var left = [Float](repeating: 0, count: 2048)
    var right = [Float](repeating: 0, count: 2048)
    var rms: Float = 0
    left.withUnsafeMutableBufferPointer { l in
      right.withUnsafeMutableBufferPointer { r in
        rms = engine.injectPlaylist(
          left: l.baseAddress!,
          right: r.baseAddress!,
          frames: 2048,
          sampleRate: 48_000
        )
      }
    }
    XCTAssertGreaterThan(
      rms,
      0.01,
      "On-air Play must mix playlist into the WHIP buffer even if the mic engine is stopped"
    )
    XCTAssertTrue(left.contains { abs($0) > 0.01 })
  }

  func testInjectPlaylistWithoutRunningEngine() throws {
    let engine = NativeAudioEngine()
    try engine.start()
    let url = try Self.writeSineCAF()
    defer {
      engine.tearDown()
      try? FileManager.default.removeItem(at: url)
    }
    let queued = expectation(description: "queue")
    engine.queueTrack(id: "t4", title: "sine-inject", url: url.path, assetId: nil) { error, _ in
      XCTAssertNil(error)
      queued.fulfill()
    }
    wait(for: [queued], timeout: 8)
    let played = expectation(description: "play")
    engine.play("t4") { error in
      XCTAssertNil(error)
      played.fulfill()
    }
    wait(for: [played], timeout: 8)
    XCTAssertTrue(engine.playlistVoicePlaying("t4"))
    var left = [Float](repeating: 0, count: 2048)
    var right = [Float](repeating: 0, count: 2048)
    var rms: Float = 0
    left.withUnsafeMutableBufferPointer { l in
      right.withUnsafeMutableBufferPointer { r in
        rms = engine.injectPlaylist(
          left: l.baseAddress!,
          right: r.baseAddress!,
          frames: 2048,
          sampleRate: 48_000
        )
      }
    }
    XCTAssertGreaterThan(rms, 0.01)
    XCTAssertTrue(left.contains { abs($0) > 0.01 })
    engine.setMutes(mic: true, playlist: nil)
    XCTAssertEqual(engine.publishOutputScale(didInjectMix: false), 0)
    XCTAssertTrue(engine.hasPlayingPlaylist)
    XCTAssertEqual(engine.publishMasterFader, 1, accuracy: 0.0001)
    engine.setGains(mic: nil, playlist: nil, master: 0.25)
    XCTAssertEqual(engine.publishMasterFader, 0.25, accuracy: 0.0001)
    var quiet = [Float](repeating: 0, count: 2048)
    var quietR = [Float](repeating: 0, count: 2048)
    var quietRms: Float = 0
    quiet.withUnsafeMutableBufferPointer { l in
      quietR.withUnsafeMutableBufferPointer { r in
        quietRms = engine.injectPlaylist(
          left: l.baseAddress!,
          right: r.baseAddress!,
          frames: 2048,
          sampleRate: 48_000
        )
      }
    }
    XCTAssertGreaterThan(rms, quietRms * 2, "Master fader must scale the playlist program send")
  }

  func testWebRtcCapturePcmIsFloatS16() {
    XCTAssertEqual(WebRtcCapturePcm.fromUnit(0.25), 8192, accuracy: 1)
    XCTAssertEqual(WebRtcCapturePcm.fromUnit(1), 32767, accuracy: 1)
    XCTAssertEqual(WebRtcCapturePcm.toUnitRms(8192), 0.25, accuracy: 0.001)
  }

  func testInjectPlaylistUsesCaptureScaleForListen() throws {
    let engine = NativeAudioEngine()
    try engine.start()
    let url = try Self.writeSineCAF()
    defer {
      engine.tearDown()
      try? FileManager.default.removeItem(at: url)
    }
    let queued = expectation(description: "queue")
    engine.queueTrack(id: "t5", title: "sine-s16", url: url.path, assetId: nil) { error, _ in
      XCTAssertNil(error)
      queued.fulfill()
    }
    wait(for: [queued], timeout: 8)
    let played = expectation(description: "play")
    engine.play("t5") { error in
      XCTAssertNil(error)
      played.fulfill()
    }
    wait(for: [played], timeout: 8)
    var left = [Float](repeating: 0, count: 2048)
    var right = [Float](repeating: 0, count: 2048)
    left.withUnsafeMutableBufferPointer { l in
      right.withUnsafeMutableBufferPointer { r in
        _ = engine.injectPlaylist(
          left: l.baseAddress!,
          right: r.baseAddress!,
          frames: 2048,
          sampleRate: 48_000,
          destScale: WebRtcCapturePcm.floatS16
        )
      }
    }
    let peak = left.map { abs($0) }.max() ?? 0
    XCTAssertGreaterThan(
      peak,
      100,
      "WHIP capture is FloatS16; unit-float playlist would be silent on Listen"
    )
  }

  static func writeSineCAF() throws -> URL {
    let format = NativeAudioEngine.playlistPlaybackFormat
    let url = FileManager.default.temporaryDirectory
      .appendingPathComponent("sml-sine-\(UUID().uuidString).caf")
    let file = try AVAudioFile(forWriting: url, settings: format.settings)
    let frames: AVAudioFrameCount = 48_000
    guard let buf = AVAudioPCMBuffer(pcmFormat: format, frameCapacity: frames) else {
      throw NSError(domain: "RunnerTests", code: 1)
    }
    buf.frameLength = frames
    let freq: Float = 440
    for i in 0..<Int(frames) {
      let s = sin(2 * Float.pi * freq * Float(i) / 48_000) * 0.25
      buf.floatChannelData![0][i] = s
      buf.floatChannelData![1][i] = s
    }
    try file.write(from: buf)
    return url
  }
}

import AVFoundation
import Cocoa
import FlutterMacOS
import XCTest

@testable import Sound_Mix_Live_Studio

/// Regression coverage for live mic hot-swap continuity (publish must not stick at silence).
class RunnerTests: XCTestCase {

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
      NativeAudioEngine.playbackCacheFileName(
        id: "asset-9",
        format: AVAudioFormat(standardFormatWithSampleRate: 44_100, channels: 2)!
      )
    )
  }
}

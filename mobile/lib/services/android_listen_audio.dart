import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Native Android helper: force WHEP listen to the **loudspeaker**.
///
/// Uses media playback routing (MODE_NORMAL / USAGE_MEDIA), not voice-call
/// mode which OEMs keep on the earpiece even when speakerOn reports true.
class AndroidListenAudio {
  AndroidListenAudio._();

  static const _channel = MethodChannel('com.livemixaudio.live_mix/audio_route');

  static bool get _isAndroid =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  /// MODE_NORMAL + media focus + speaker; schedules short re-asserts.
  static Future<void> preparePlayback() async {
    if (!_isAndroid) return;
    try {
      final result = await _channel.invokeMethod<dynamic>('preparePlayback');
      debugPrint('AndroidListenAudio.preparePlayback => $result');
    } catch (e) {
      debugPrint('AndroidListenAudio.preparePlayback failed: $e');
    }
  }

  /// Re-apply loudspeaker after track attach / resume / ICE.
  static Future<void> forceSpeaker() async {
    if (!_isAndroid) return;
    try {
      final result = await _channel.invokeMethod<dynamic>('forceSpeaker');
      debugPrint('AndroidListenAudio.forceSpeaker => $result');
    } catch (e) {
      debugPrint('AndroidListenAudio.forceSpeaker failed: $e');
    }
  }

  static Future<void> releasePlayback() async {
    if (!_isAndroid) return;
    try {
      await _channel.invokeMethod<bool>('releasePlayback');
    } catch (_) {}
  }
}

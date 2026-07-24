import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

/// Keeps WHEP/WebRTC listen audio alive when Android hibernates the app.
///
/// Starts a `mediaPlayback` foreground service with a persistent notification.
/// Failures are swallowed — foreground playback must keep working even if the
/// notification / FGS path is blocked by the OEM or permission state.
/// No-op on non-Android platforms.
class ListenPlaybackService {
  ListenPlaybackService._();

  static const _channel = MethodChannel('com.livemixaudio.live_mix/listen_fg');
  static const _events =
      EventChannel('com.livemixaudio.live_mix/listen_fg_events');
  static bool _active = false;
  static StreamSubscription? _eventSub;
  static final _stopController = StreamController<void>.broadcast();

  static bool get isActive => _active;

  /// Fired when the user taps Stop on the Android listen notification.
  static Stream<void> get onStopRequested {
    _ensureEventListen();
    return _stopController.stream;
  }

  static void _ensureEventListen() {
    if (_eventSub != null || kIsWeb || !Platform.isAndroid) return;
    _eventSub = _events.receiveBroadcastStream().listen((event) {
      if (event == 'stop') {
        _active = false;
        if (!_stopController.isClosed) _stopController.add(null);
      }
    });
  }

  static Future<void> start({
    required String title,
    String? artist,
  }) async {
    if (kIsWeb || !Platform.isAndroid) return;
    _ensureEventListen();

    try {
      // Android 13+ notification permission for the FG notification.
      // Never block playback if the user denies — FGS may still start.
      final notif = await Permission.notification.status;
      if (!notif.isGranted && !notif.isPermanentlyDenied) {
        await Permission.notification.request();
      }
    } catch (_) {}

    try {
      await _channel.invokeMethod('start', {
        'title': title,
        'artist': artist ?? 'Sound Mix Live',
      });
      _active = true;
    } catch (e, st) {
      // Playback continues in-foreground even if FG start fails.
      debugPrint('ListenPlaybackService.start failed: $e\n$st');
      _active = false;
    }
  }

  static Future<void> update({
    required String title,
    String? artist,
    bool playing = true,
  }) async {
    if (kIsWeb || !Platform.isAndroid || !_active) return;
    try {
      await _channel.invokeMethod('update', {
        'title': title,
        'artist': artist ?? 'Sound Mix Live',
        'playing': playing,
      });
    } catch (e) {
      debugPrint('ListenPlaybackService.update failed: $e');
    }
  }

  static Future<void> stop() async {
    if (kIsWeb || !Platform.isAndroid) {
      _active = false;
      return;
    }
    try {
      await _channel.invokeMethod('stop');
    } catch (e) {
      debugPrint('ListenPlaybackService.stop failed: $e');
    }
    _active = false;
  }
}

import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models.dart';

enum MixerPublishState { idle, connecting, connected, failed }

/// Native macOS AVAudioEngine mixer + WHIP (MethodChannel).
class MixerBridge extends ChangeNotifier {
  static const _channel = MethodChannel('soundmix/mixer_engine');
  static const _prefsMicDeviceKey = 'studio_desktop.mic_device_id';

  bool _ready = false;
  bool _armed = false;
  double _level = 0;
  double _peak = 0;
  double _micLevel = 0;
  double _playlistLevel = 0;
  MixerPublishState _publish = MixerPublishState.idle;
  String? _iceState;
  String? _status;
  String? _error;
  void Function(String words, bool isFinal)? onScriptureSpeech;
  void Function(String status)? onScriptureSpeechStatus;
  void Function(String message)? onScriptureSpeechError;
  List<MixerTrack> _tracks = const [];
  List<AudioInputDevice> _inputs = const [];
  List<AudioInputDevice> _outputs = const [];
  String? _selectedDeviceId;
  String? _selectedOutputDeviceId;
  String _micPermission = 'notDetermined';
  bool _listening = false;

  bool get isReady => _ready;
  bool get isArmed => _armed;
  /// `authorized` | `denied` | `notDetermined`
  String get micPermission => _micPermission;
  bool get micAuthorized => _micPermission == 'authorized';
  bool get needsMicPermissionPrompt => _micPermission == 'notDetermined';
  double get level => _level;
  double get peak => _peak;
  double get micLevel => _micLevel;
  double get playlistLevel => _playlistLevel;
  MixerPublishState get publish => _publish;
  String? get iceState => _iceState;
  String? get status => _status;
  String? get error => _error;
  List<MixerTrack> get tracks => _tracks;
  List<AudioInputDevice> get inputs => _inputs;
  List<AudioInputDevice> get outputs => _outputs;
  String? get selectedDeviceId => _selectedDeviceId;
  String? get selectedOutputDeviceId => _selectedOutputDeviceId;

  void _ensureListen() {
    if (_listening) return;
    _listening = true;
    _channel.setMethodCallHandler((call) async {
      if (call.method == 'hostMessage' && call.arguments is String) {
        _onHostMessage(call.arguments as String);
      }
    });
  }

  Future<String> refreshMicPermissionStatus() async {
    _ensureListen();
    try {
      final raw = await _channel.invokeMethod<dynamic>('micPermissionStatus');
      if (raw is Map && raw['status'] is String) {
        _micPermission = raw['status'] as String;
        notifyListeners();
        return _micPermission;
      }
    } catch (_) {}
    return _micPermission;
  }

  /// OS dialog only when status is still `notDetermined` (first run).
  Future<bool> requestMicAccess() async {
    _ensureListen();
    final status = await refreshMicPermissionStatus();
    if (status == 'authorized') return true;
    if (status == 'denied') return false;
    try {
      final raw = await _channel.invokeMethod<dynamic>('requestMicAccess');
      if (raw is Map) {
        if (raw['status'] is String) {
          _micPermission = raw['status'] as String;
        }
        final granted = raw['granted'] == true;
        notifyListeners();
        return granted;
      }
    } catch (_) {}
    await refreshMicPermissionStatus();
    return micAuthorized;
  }

  /// Use mic without re-prompting when already authorized (Mixlr-style).
  Future<bool> ensureMicAccess({bool promptIfNeeded = false}) async {
    final status = await refreshMicPermissionStatus();
    if (status == 'authorized') return true;
    if (status == 'denied') return false;
    if (!promptIfNeeded) return false;
    return requestMicAccess();
  }

  Future<String?> savedMicDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_prefsMicDeviceKey);
  }

  Future<void> _persistMicDevice(String deviceId) async {
    final prefs = await SharedPreferences.getInstance();
    if (deviceId == 'none') {
      await prefs.remove(_prefsMicDeviceKey);
    } else {
      await prefs.setString(_prefsMicDeviceKey, deviceId);
    }
  }

  Future<void> startEngine() async {
    _ensureListen();
    _ready = false;
    _status = 'Starting native mixer…';
    notifyListeners();
    await _channel.invokeMethod<void>('startEngine');
    _ready = true;
    _status = 'Native mixer ready';
    notifyListeners();
  }

  /// Legacy alias — embed URL is ignored (native engine).
  Future<void> load(String embedUrl) => startEngine();

  Future<void> armMic({String? deviceId}) async {
    final raw = await _channel.invokeMethod<dynamic>('armMic', {
      if (deviceId != null) 'deviceId': deviceId,
    });
    _armed = true;
    _error = null;
    if (raw is Map && raw['selected'] is String) {
      _selectedDeviceId = raw['selected'] as String;
      await _persistMicDevice(_selectedDeviceId!);
    } else if (deviceId != null) {
      _selectedDeviceId = deviceId;
      await _persistMicDevice(deviceId);
    }
    notifyListeners();
  }

  Future<void> listDevices() async {
    await _channel.invokeMethod<dynamic>('listDevices');
  }

  Future<void> setInputDevice(String deviceId) async {
    // Optimistic UI — native also pins selection before rebuild so it can't snap back.
    _selectedDeviceId = deviceId;
    if (deviceId == 'none') {
      _armed = false;
      _micLevel = 0;
    } else {
      _armed = true;
    }
    notifyListeners();
    await _channel.invokeMethod<void>('setInputDevice', deviceId);
    await _persistMicDevice(deviceId);
  }

  Future<void> setOutputDevice(String deviceId) async {
    _selectedOutputDeviceId = deviceId;
    notifyListeners();
    await _channel.invokeMethod<void>('setOutputDevice', deviceId);
  }

  Future<void> listOutputs() async {
    await _channel.invokeMethod<dynamic>('listOutputs');
  }

  Future<void> reloadDevices() async {
    await _channel.invokeMethod<void>('reloadDevices');
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  Future<void> setGains({double? mic, double? playlist, double? master}) {
    return _channel.invokeMethod<void>('setGains', {
      if (mic != null) 'mic': mic,
      if (playlist != null) 'playlist': playlist,
      if (master != null) 'master': master,
    });
  }

  Future<void> setMutes({bool? mic, bool? playlist}) {
    return _channel.invokeMethod<void>('setMutes', {
      if (mic != null) 'mic': mic,
      if (playlist != null) 'playlist': playlist,
    });
  }

  Future<void> setCues({bool? mic, bool? playlist}) {
    return _channel.invokeMethod<void>('setCues', {
      if (mic != null) 'mic': mic,
      if (playlist != null) 'playlist': playlist,
    });
  }

  Future<void> queueTrack(LibraryAsset asset) {
    return _channel.invokeMethod<void>('queueTrack', {
      'id': 'asset-${asset.id}',
      'title': asset.title,
      'url': asset.url,
      'assetId': asset.id,
    });
  }

  Future<void> play(String id) => _channel.invokeMethod<void>('play', id);
  Future<void> pause(String id) => _channel.invokeMethod<void>('pause', id);
  Future<void> restart(String id) => _channel.invokeMethod<void>('restart', id);
  Future<void> remove(String id) => _channel.invokeMethod<void>('remove', id);

  Future<void> goLive(String whipUrl) async {
    _publish = MixerPublishState.connecting;
    _error = null;
    notifyListeners();
    try {
      await _channel
          .invokeMethod<void>('goLive', whipUrl)
          .timeout(
            const Duration(seconds: 25),
            onTimeout: () => throw Exception(
              'Go live timed out — WHIP publish did not finish. Try again.',
            ),
          );
      _publish = MixerPublishState.connected;
      _error = null;
    } on PlatformException catch (e) {
      _publish = MixerPublishState.failed;
      _error = e.message?.isNotEmpty == true
          ? e.message!
          : 'WHIP publish failed. Check network and try again.';
      throw Exception(_error);
    } catch (e) {
      _publish = MixerPublishState.failed;
      _error = e.toString().replaceFirst('Exception: ', '');
      rethrow;
    } finally {
      notifyListeners();
    }
  }

  Future<bool> startScriptureListen({String localeId = 'en_US'}) async {
    _ensureListen();
    try {
      final raw = await _channel.invokeMethod<dynamic>('startScriptureListen', {
        'localeId': localeId,
      });
      if (raw is Map && raw['ok'] == true) return true;
      return raw == true;
    } on PlatformException catch (e) {
      onScriptureSpeechError?.call(
        e.message?.isNotEmpty == true
            ? e.message!
            : 'Speech recognition unavailable. Type a reference instead.',
      );
      return false;
    } catch (_) {
      onScriptureSpeechError?.call(
        'Speech recognition unavailable. Type a reference instead.',
      );
      return false;
    }
  }

  Future<void> stopScriptureListen() async {
    try {
      await _channel.invokeMethod<void>('stopScriptureListen');
    } catch (_) {}
  }

  Future<void> stopPublish() async {
    try {
      await _channel.invokeMethod<void>('stopPublish');
    } catch (_) {}
    _publish = MixerPublishState.idle;
    notifyListeners();
  }

  void _onHostMessage(String message) {
    try {
      final data = jsonDecode(message) as Map<String, dynamic>;
      final type = data['type'] as String? ?? '';
      switch (type) {
        case 'ready':
          _ready = true;
          _status = 'Native mixer ready';
          break;
        case 'levels':
          _level = ((data['master'] as num?)?.toDouble() ?? 0).clamp(0.0, 1.0);
          _micLevel = ((data['mic'] as num?)?.toDouble() ?? 0).clamp(0.0, 1.0);
          _playlistLevel =
              ((data['playlist'] as num?)?.toDouble() ?? 0).clamp(0.0, 1.0);
          _peak = _level > _peak ? _level : (_peak * 0.92);
          break;
        case 'tracks':
          final list = data['tracks'] as List<dynamic>? ?? [];
          _tracks = list
              .map((e) => MixerTrack.fromJson(e as Map<String, dynamic>))
              .toList();
          break;
        case 'devices':
          final list = data['inputs'] as List<dynamic>? ?? [];
          _inputs = list
              .map((e) => AudioInputDevice.fromJson(e as Map<String, dynamic>))
              .toList();
          _selectedDeviceId = data['selected'] as String?;
          break;
        case 'outputs':
          final list = data['outputs'] as List<dynamic>? ?? [];
          _outputs = list
              .map((e) => AudioInputDevice.fromJson(e as Map<String, dynamic>))
              .toList();
          _selectedOutputDeviceId = data['selected'] as String?;
          break;
        case 'status':
          _status = data['message'] as String?;
          _error = null;
          break;
        case 'publish':
          final state = data['state'] as String? ?? 'idle';
          _publish = switch (state) {
            'connecting' => MixerPublishState.connecting,
            'connected' => MixerPublishState.connected,
            'failed' => MixerPublishState.failed,
            _ => MixerPublishState.idle,
          };
          if (_publish == MixerPublishState.failed) {
            final msg = data['message'] as String?;
            _error = (msg != null && msg.isNotEmpty)
                ? msg
                : 'WHIP publish failed. Check network and try Go live again.';
          }
          break;
        case 'ice':
          _iceState = data['state'] as String?;
          break;
        case 'scriptureSpeech':
          onScriptureSpeech?.call(
            data['words'] as String? ?? '',
            data['final'] == true,
          );
          return;
        case 'scriptureSpeechStatus':
          onScriptureSpeechStatus?.call(data['status'] as String? ?? '');
          return;
        case 'scriptureSpeechError':
          onScriptureSpeechError?.call(
            data['message'] as String? ?? 'Speech recognition failed.',
          );
          return;
        case 'error':
          final msg = data['message'] as String?;
          if (msg != null && msg.isNotEmpty) {
            _error = msg;
          }
          break;
      }
      notifyListeners();
    } catch (e) {
      if (kDebugMode) {
        debugPrint('mixer host message parse failed: $e');
      }
    }
  }

  @override
  void dispose() {
    stopPublish();
    _channel.invokeMethod<void>('dispose').catchError((_) {});
    _channel.setMethodCallHandler(null);
    _listening = false;
    super.dispose();
  }
}

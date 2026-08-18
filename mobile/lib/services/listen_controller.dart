import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:just_audio/just_audio.dart';
import 'package:uuid/uuid.dart';

import '../models/models.dart';
import '../widgets/permission_disclosure.dart';
import 'api_client.dart';
import 'auth_state.dart';
import 'listen_playback_service.dart';
import 'network_status.dart';
import 'selected_channel.dart';
import 'whep_listener.dart';

enum ListenMode { none, whep, hls }

/// App-scoped listen session so leaving ListenScreen does not kill audio.
class ListenController extends ChangeNotifier with WidgetsBindingObserver {
  ListenController({
    required AuthState auth,
    required NetworkStatus network,
    required SelectedChannel channel,
  })  : _auth = auth,
        _network = network,
        _channel = channel;

  final AuthState _auth;
  final NetworkStatus _network;
  final SelectedChannel _channel;

  final AudioPlayer _player = AudioPlayer();
  final WhepListener _whep = WhepListener();
  final _sessionKey = const Uuid().v4();

  String? _streamUuid;
  ListenPayload? _payload;
  String? _error;
  bool _loading = false;
  bool _playing = false;
  bool _connecting = false;
  bool _hasStartedPlayback = false;
  bool _hasUserPaused = false;
  bool _userWantsPlay = true;
  ListenMode _mode = ListenMode.none;
  int _listeners = 0;
  int _likes = 0;
  bool _scriptureEnabled = false;
  ScriptureCue? _scripture;
  Duration _listened = Duration.zero;
  double _pulse = 0.2;
  bool _uiAttached = false;
  bool _observed = false;

  Timer? _presenceTimer;
  Timer? _scriptureTimer;
  Timer? _listenTick;
  StreamSubscription<WhepConnectionState>? _whepSub;
  StreamSubscription<PlayerState>? _hlsSub;
  StreamSubscription? _fgStopSub;
  DateTime? _listenStartedAt;
  DateTime? _lastLoadAt;
  bool _loadInFlight = false;

  static const _presencePoll = Duration(seconds: 30);
  static const _scripturePoll = Duration(seconds: 3);
  static const _loadCooldown = Duration(seconds: 2);

  String? get streamUuid => _streamUuid;
  ListenPayload? get payload => _payload;
  String? get error => _error;
  bool get loading => _loading;
  bool get playing => _playing;
  bool get connecting => _connecting;
  bool get hasStartedPlayback => _hasStartedPlayback;
  bool get hasUserPaused => _hasUserPaused;
  bool get userWantsPlay => _userWantsPlay;
  ListenMode get mode => _mode;
  int get listeners => _listeners;
  int get likes => _likes;
  bool get scriptureEnabled => _scriptureEnabled;
  ScriptureCue? get scripture => _scripture;
  Duration get listened => _listened;
  double get pulse => _pulse;
  bool get uiAttached => _uiAttached;
  WhepListener get whep => _whep;
  RTCVideoRenderer? get whepRenderer => _whep.renderer;

  /// True when a listen session exists (playing, paused, or connecting).
  bool get hasSession =>
      _streamUuid != null && (_payload != null || _loading || _error != null);

  /// Show mini-player when audio session is alive but ListenScreen is not open.
  bool get showMiniPlayer =>
      hasSession &&
      !_uiAttached &&
      (_playing || _hasUserPaused || _connecting || _userWantsPlay);

  String get statusLabel {
    if (_loading || _connecting) {
      if (_mode == ListenMode.whep &&
          _whep.connectionState == WhepConnectionState.reconnecting) {
        return 'RECONNECTING';
      }
      return 'CONNECTING';
    }
    if (_mode == ListenMode.whep &&
        _whep.connectionState == WhepConnectionState.reconnecting) {
      return 'RECONNECTING';
    }
    if (_playing) return 'LISTENING';
    if (_hasUserPaused || (_hasStartedPlayback && !_userWantsPlay)) {
      return 'PAUSED';
    }
    return 'TAP PLAY';
  }

  void ensureObserved() {
    if (_observed) return;
    _observed = true;
    WidgetsBinding.instance.addObserver(this);
    _whepSub = _whep.connectionStates.listen(_onWhepConnection);
    _hlsSub = _player.playerStateStream.listen(_onHlsPlayerState);
    _fgStopSub = ListenPlaybackService.onStopRequested.listen((_) {
      unawaited(stopFromNotification());
    });
    _listenTick = Timer.periodic(const Duration(milliseconds: 120), (_) {
      if (!_playing) return;
      _pulse = 0.15 + (DateTime.now().millisecond % 700) / 1000;
      if (_listenStartedAt != null) {
        _listened = DateTime.now().difference(_listenStartedAt!);
      }
      notifyListeners();
    });
  }

  void attachUi() {
    ensureObserved();
    if (_uiAttached) return;
    _uiAttached = true;
    notifyListeners();
  }

  void detachUi() {
    if (!_uiAttached) return;
    _uiAttached = false;
    notifyListeners();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    if (_mode == ListenMode.whep && _userWantsPlay) {
      unawaited(_whep.ensureLoudspeaker());
    }
    if (_userWantsPlay &&
        _mode == ListenMode.whep &&
        !_whep.isConnected &&
        _error == null &&
        !_loading &&
        _streamUuid != null) {
      unawaited(load());
    }
  }

  /// Open or reattach to [streamUuid]. Reloads only when switching channels
  /// or when there is no live session yet.
  Future<void> open(String streamUuid, {BuildContext? context}) async {
    ensureObserved();
    final same =
        _streamUuid == streamUuid && (_payload != null || _loading);
    if (same) {
      notifyListeners();
      return;
    }
    _streamUuid = streamUuid;
    await load(context: context);
  }

  Future<void> load({BuildContext? context}) async {
    final uuid = _streamUuid;
    if (uuid == null || uuid.isEmpty) return;
    if (_loadInFlight) return;
    final now = DateTime.now();
    if (_lastLoadAt != null && now.difference(_lastLoadAt!) < _loadCooldown) {
      return;
    }
    _loadInFlight = true;
    _lastLoadAt = now;

    _loading = true;
    _connecting = true;
    _error = null;
    _playing = false;
    _hasUserPaused = false;
    _userWantsPlay = true;
    notifyListeners();

    try {
      try {
        await ListenPlaybackService.stop();
      } catch (_) {}
      await _stopMedia();
      final payload =
          await _auth.api.listen(uuid).timeout(const Duration(seconds: 15));
      _payload = payload;
      _channel.select(
        uuid: payload.uuid,
        title: payload.title,
        organization: payload.orgName,
        artworkUrl: payload.artworkUrl,
        creatorType: payload.creatorType,
      );
      notifyListeners();

      if (!payload.isLive) {
        _loading = false;
        _connecting = false;
        _error = 'This channel is offline right now.';
        if (payload.isChurch) _scriptureEnabled = true;
        notifyListeners();
        unawaited(refreshScripture());
        _scriptureTimer?.cancel();
        _scriptureTimer = Timer.periodic(
          _scripturePoll,
          (_) => unawaited(refreshScripture()),
        );
        return;
      }

      _loading = false;
      _connecting = true;
      if (payload.isChurch) _scriptureEnabled = true;
      notifyListeners();

      final started = await _startMedia(payload);
      if (started) {
        _listenStartedAt = DateTime.now();
        _playing = true;
        _connecting = false;
        _hasStartedPlayback = true;
        _hasUserPaused = false;
        _error = null;
        notifyListeners();
        final ctx = context;
        unawaited(syncForegroundService(
          playing: true,
          context: (ctx != null && ctx.mounted) ? ctx : null,
        ));
      } else {
        _connecting = false;
        _error =
            'Could not connect to the live audio. Check your connection and tap Retry.';
        notifyListeners();
      }

      unawaited(pingPresence());
      unawaited(refreshScripture());
      _presenceTimer?.cancel();
      _presenceTimer = Timer.periodic(
        _presencePoll,
        (_) => unawaited(pingPresence()),
      );
      _scriptureTimer?.cancel();
      _scriptureTimer = Timer.periodic(
        _scripturePoll,
        (_) => unawaited(refreshScripture()),
      );
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      _connecting = false;
      notifyListeners();
    } on TimeoutException {
      _error = 'Timed out connecting to this stream. Tap Retry.';
      _loading = false;
      _connecting = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _loading = false;
      _connecting = false;
      notifyListeners();
    } finally {
      _loadInFlight = false;
    }
  }

  Future<bool> _startMedia(ListenPayload payload) async {
    final preferHls = payload.preferHls || payload.playbackMode == 'hls';

    if (preferHls) {
      final hlsOk = await _tryHls(payload.hlsUrl);
      if (hlsOk) return true;
      return _tryWhep(payload.whepUrl);
    }

    final whepOk = await _tryWhep(payload.whepUrl);
    if (whepOk) return true;
    return _tryHls(payload.hlsUrl);
  }

  Future<bool> _tryWhep(String? whepUrl) async {
    if (whepUrl == null || whepUrl.isEmpty) return false;
    try {
      _mode = ListenMode.whep;
      await _whep.start(whepUrl).timeout(const Duration(seconds: 25));
      if (_whep.isConnected) return true;
      throw Exception('WHEP connected without audio readiness.');
    } catch (e) {
      debugPrint('WHEP listen failed: $e');
      _mode = ListenMode.none;
      try {
        await _whep.stop();
      } catch (_) {}
      return false;
    }
  }

  Future<bool> _tryHls(String? hlsUrl) async {
    if (hlsUrl == null || hlsUrl.isEmpty) return false;
    final opusOnly = await _hlsLooksLikeOpus(hlsUrl);
    if (opusOnly) {
      debugPrint('Skipping Opus HLS (not playable in ExoPlayer).');
      return false;
    }
    try {
      _mode = ListenMode.hls;
      await _player.setUrl(hlsUrl).timeout(const Duration(seconds: 12));
      await _player.play().timeout(const Duration(seconds: 8));
      return _player.playing;
    } catch (e) {
      debugPrint('HLS listen failed: $e');
      _mode = ListenMode.none;
      try {
        await _player.stop();
      } catch (_) {}
      return false;
    }
  }

  Future<bool> _hlsLooksLikeOpus(String hlsUrl) async {
    try {
      final res = await http
          .get(Uri.parse(hlsUrl))
          .timeout(const Duration(seconds: 6));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        return true;
      }
      final body = res.body.toLowerCase();
      return body.contains('codecs="opus"') ||
          body.contains("codecs='opus'") ||
          body.contains('opus');
    } catch (_) {
      return true;
    }
  }

  void _onWhepConnection(WhepConnectionState state) {
    if (_mode != ListenMode.whep) return;
    switch (state) {
      case WhepConnectionState.connected:
        if (_userWantsPlay && _whep.isConnected) {
          _listenStartedAt ??= DateTime.now();
          _playing = true;
          _connecting = false;
          _hasStartedPlayback = true;
          _hasUserPaused = false;
          _error = null;
          notifyListeners();
          unawaited(syncForegroundService(playing: true));
        }
        break;
      case WhepConnectionState.reconnecting:
        if (!_playing && _userWantsPlay) {
          _connecting = true;
          notifyListeners();
        }
        break;
      case WhepConnectionState.failed:
      case WhepConnectionState.closed:
        _playing = false;
        _connecting = false;
        _error = 'Live audio dropped. Check your connection and tap Retry.';
        notifyListeners();
        unawaited(ListenPlaybackService.stop());
        break;
      case WhepConnectionState.connecting:
        if (_userWantsPlay) {
          _connecting = true;
          notifyListeners();
        }
        break;
      case WhepConnectionState.idle:
        break;
    }
  }

  void _onHlsPlayerState(PlayerState state) {
    if (_mode != ListenMode.hls) return;
    final playing = state.playing;
    if (state.processingState == ProcessingState.completed ||
        state.processingState == ProcessingState.idle) {
      if (_playing) {
        _playing = false;
        notifyListeners();
        unawaited(ListenPlaybackService.stop());
      }
      return;
    }
    if (playing != _playing) {
      _playing = playing;
      notifyListeners();
      unawaited(syncForegroundService(playing: playing));
    }
  }

  Future<void> syncForegroundService({
    required bool playing,
    BuildContext? context,
  }) async {
    final p = _payload;
    if (p == null || !_userWantsPlay) {
      if (!playing) {
        try {
          await ListenPlaybackService.stop();
        } catch (_) {}
      }
      return;
    }
    try {
      if (playing) {
        if (ListenPlaybackService.isActive) {
          await ListenPlaybackService.update(
            title: p.title,
            artist: p.orgName,
            playing: true,
          );
        } else {
          if (context != null && context.mounted) {
            await PermissionDisclosure.ensureNotifications(context);
          }
          await ListenPlaybackService.start(
            title: p.title,
            artist: p.orgName,
          );
        }
      } else if (ListenPlaybackService.isActive) {
        await ListenPlaybackService.update(
          title: p.title,
          artist: p.orgName,
          playing: false,
        );
      }
    } catch (e) {
      debugPrint('Foreground listen service sync failed: $e');
    }
  }

  Future<void> pingPresence() async {
    final uuid = _streamUuid;
    if (uuid == null || !_network.hasLink) return;
    try {
      final data = await _auth.api
          .presence(uuid, sessionKey: _sessionKey)
          .timeout(const Duration(seconds: 8));
      _listeners = data['listeners'] as int? ?? _listeners;
      _likes = data['likes'] as int? ?? _likes;
      notifyListeners();
    } catch (_) {}
  }

  Future<void> refreshScripture() async {
    final uuid = _streamUuid;
    final payload = _payload;
    if (uuid == null) return;
    if (payload?.creatorType != null && !payload!.isChurch) {
      if (_scriptureEnabled || _scripture != null) {
        _scriptureEnabled = false;
        _scripture = null;
        notifyListeners();
      }
      return;
    }
    if (payload?.isChurch == true && !_scriptureEnabled) {
      _scriptureEnabled = true;
      notifyListeners();
    }
    if (!_network.hasLink) return;
    try {
      final result =
          await _auth.api.scripture(uuid).timeout(const Duration(seconds: 8));
      _scriptureEnabled = result.enabled;
      _scripture = result.cue;
      notifyListeners();
      if (result.enabled) {
        _channel.select(
          uuid: uuid,
          title: payload?.title,
          organization: payload?.orgName,
          artworkUrl: payload?.artworkUrl,
          creatorType: 'church',
        );
      }
    } catch (_) {}
  }

  Future<void> stopFromNotification() async {
    _userWantsPlay = false;
    if (_mode == ListenMode.whep) {
      await _whep.setEnabled(false);
    } else if (_mode == ListenMode.hls) {
      try {
        await _player.pause();
      } catch (_) {}
    }
    _playing = false;
    _connecting = false;
    _hasUserPaused = true;
    notifyListeners();
    try {
      await ListenPlaybackService.stop();
    } catch (_) {}
  }

  Future<void> togglePlay({BuildContext? context}) async {
    if (_connecting && !_playing) return;

    if (_mode == ListenMode.whep) {
      final next = !_playing;
      _userWantsPlay = next;
      if (next) {
        _connecting = true;
        _hasUserPaused = false;
        _error = null;
        notifyListeners();
        if (!_whep.isConnected) {
          _lastLoadAt = null;
          await load(context: context);
          return;
        }
        try {
          await _whep.setEnabled(true);
        } catch (e) {
          _connecting = false;
          _error = 'Could not resume audio. Tap Retry.';
          notifyListeners();
          return;
        }
        _listenStartedAt ??= DateTime.now();
        _playing = true;
        _connecting = false;
        _hasStartedPlayback = true;
        notifyListeners();
        final ctx = context;
        unawaited(syncForegroundService(
          playing: true,
          context: (ctx != null && ctx.mounted) ? ctx : null,
        ));
      } else {
        try {
          await _whep.setEnabled(false);
        } catch (_) {}
        _playing = false;
        _connecting = false;
        _hasUserPaused = true;
        notifyListeners();
        unawaited(syncForegroundService(playing: false));
      }
      return;
    }

    if (_mode == ListenMode.none) {
      await load(context: context);
      return;
    }

    if (_playing) {
      _userWantsPlay = false;
      try {
        await _player.pause();
      } catch (_) {}
      _playing = false;
      _hasUserPaused = true;
      notifyListeners();
      unawaited(syncForegroundService(playing: false));
    } else {
      _userWantsPlay = true;
      _connecting = true;
      _hasUserPaused = false;
      notifyListeners();
      try {
        await _player.play();
      } catch (e) {
        _connecting = false;
        _error = 'Could not resume audio. Tap Retry.';
        notifyListeners();
        return;
      }
      _listenStartedAt ??= DateTime.now();
      _playing = true;
      _connecting = false;
      _hasStartedPlayback = true;
      notifyListeners();
      final ctx = context;
      unawaited(syncForegroundService(
        playing: true,
        context: (ctx != null && ctx.mounted) ? ctx : null,
      ));
    }
  }

  Future<void> like({
    required BuildContext context,
    required Future<bool> Function() ensureLoggedIn,
  }) async {
    final uuid = _streamUuid;
    if (uuid == null) return;
    if (!_auth.isLoggedIn) {
      final ok = await ensureLoggedIn();
      if (!ok) return;
    }
    if (!_network.hasLink) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('You’re offline — likes need a connection.'),
          ),
        );
      }
      return;
    }
    try {
      final likes = await _auth.api.like(uuid);
      _likes = likes;
      notifyListeners();
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  /// Fully end the session (mini-player Stop / leave channel).
  Future<void> stopSession() async {
    _userWantsPlay = false;
    _playing = false;
    _connecting = false;
    _hasUserPaused = false;
    _presenceTimer?.cancel();
    _scriptureTimer?.cancel();
    try {
      await ListenPlaybackService.stop();
    } catch (_) {}
    await _stopMedia();
    _payload = null;
    _streamUuid = null;
    _error = null;
    _loading = false;
    _listened = Duration.zero;
    _listenStartedAt = null;
    _scriptureEnabled = false;
    _scripture = null;
    _listeners = 0;
    _likes = 0;
    notifyListeners();
  }

  Future<void> _stopMedia() async {
    _mode = ListenMode.none;
    try {
      await _player.stop();
    } catch (_) {}
    try {
      await _whep.stop();
    } catch (_) {}
  }

  @override
  void dispose() {
    if (_observed) {
      WidgetsBinding.instance.removeObserver(this);
    }
    _presenceTimer?.cancel();
    _scriptureTimer?.cancel();
    _listenTick?.cancel();
    unawaited(_whepSub?.cancel() ?? Future.value());
    unawaited(_hlsSub?.cancel() ?? Future.value());
    unawaited(_fgStopSub?.cancel() ?? Future.value());
    unawaited(() async {
      await ListenPlaybackService.stop();
      await _stopMedia();
      await _whep.dispose();
    }());
    _player.dispose();
    super.dispose();
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:just_audio/just_audio.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/listen_playback_service.dart';
import '../services/network_status.dart';
import '../services/selected_channel.dart';
import '../services/whep_listener.dart';
import '../theme.dart';
import '../widgets/network_banner.dart';
import '../widgets/signal_meter.dart';
import 'login_screen.dart';

enum _ListenMode { none, whep, hls }

class ListenScreen extends StatefulWidget {
  const ListenScreen({super.key, required this.streamUuid});

  final String streamUuid;

  @override
  State<ListenScreen> createState() => _ListenScreenState();
}

class _ListenScreenState extends State<ListenScreen>
    with WidgetsBindingObserver {
  final _player = AudioPlayer();
  final _whep = WhepListener();
  final _sessionKey = const Uuid().v4();
  ListenPayload? _payload;
  String? _error;
  bool _loading = true;
  bool _playing = false;
  bool _connecting = false;
  /// True only after the user (or auto-start) has actually begun playback once.
  bool _hasStartedPlayback = false;
  /// True after a real user pause — used so we never show PAUSED as the idle default.
  bool _hasUserPaused = false;
  bool _userWantsPlay = true;
  _ListenMode _mode = _ListenMode.none;
  int _listeners = 0;
  int _likes = 0;
  Timer? _presenceTimer;
  Timer? _scriptureTimer;
  Timer? _listenTick;
  StreamSubscription<WhepConnectionState>? _whepSub;
  StreamSubscription<PlayerState>? _hlsSub;
  StreamSubscription? _fgStopSub;
  DateTime? _listenStartedAt;
  DateTime? _lastLoadAt;
  bool _loadInFlight = false;
  Duration _listened = Duration.zero;
  double _pulse = 0.2;
  bool _scriptureEnabled = false;
  ScriptureCue? _scripture;

  /// Presence / scripture polls — keep chatty enough for live cues, not enough
  /// to burn shared NAT / church-Wi‑Fi rate-limit budgets on refresh.
  static const _presencePoll = Duration(seconds: 30);
  static const _scripturePoll = Duration(seconds: 15);
  static const _loadCooldown = Duration(seconds: 2);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _whepSub = _whep.connectionStates.listen(_onWhepConnection);
    _hlsSub = _player.playerStateStream.listen(_onHlsPlayerState);
    _fgStopSub = ListenPlaybackService.onStopRequested.listen((_) {
      unawaited(_stopFromNotification());
    });
    _load();
    _listenTick = Timer.periodic(const Duration(milliseconds: 120), (_) {
      if (!mounted || !_playing) return;
      setState(() {
        _pulse = 0.15 + (DateTime.now().millisecond % 700) / 1000;
        if (_listenStartedAt != null) {
          _listened = DateTime.now().difference(_listenStartedAt!);
        }
      });
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Keep WHEP alive while the user intends to listen. Android process
    // survival comes from ListenPlaybackService (mediaPlayback FGS).
    if (state != AppLifecycleState.resumed) return;
    if (_mode == _ListenMode.whep && _userWantsPlay) {
      // Loudspeaker often resets to earpiece after backgrounding.
      unawaited(_whep.ensureLoudspeaker());
    }
    if (_userWantsPlay &&
        _mode == _ListenMode.whep &&
        !_whep.isConnected &&
        _error == null &&
        !_loading) {
      unawaited(_load());
    }
  }

  void _onWhepConnection(WhepConnectionState state) {
    if (!mounted || _mode != _ListenMode.whep) return;
    switch (state) {
      case WhepConnectionState.connected:
        // Only LISTENING after WhepListener verified ICE + audio track.
        if (_userWantsPlay && _whep.isConnected) {
          _listenStartedAt ??= DateTime.now();
          setState(() {
            _playing = true;
            _connecting = false;
            _hasStartedPlayback = true;
            _hasUserPaused = false;
            _error = null;
          });
          unawaited(_syncForegroundService(playing: true));
        }
        break;
      case WhepConnectionState.reconnecting:
        // Hold LISTENING through brief ICE blips (matches web listen.js).
        // Only show reconnecting chrome if we already lost the playing flag.
        if (!_playing && _userWantsPlay) {
          setState(() => _connecting = true);
        }
        break;
      case WhepConnectionState.failed:
      case WhepConnectionState.closed:
        setState(() {
          _playing = false;
          _connecting = false;
          _error =
              'Live audio dropped. Check your connection and tap Retry.';
        });
        unawaited(ListenPlaybackService.stop());
        break;
      case WhepConnectionState.connecting:
        if (_userWantsPlay) {
          setState(() => _connecting = true);
        }
        break;
      case WhepConnectionState.idle:
        break;
    }
  }

  void _onHlsPlayerState(PlayerState state) {
    if (!mounted || _mode != _ListenMode.hls) return;
    final playing = state.playing;
    if (state.processingState == ProcessingState.completed ||
        state.processingState == ProcessingState.idle) {
      if (_playing) {
        setState(() => _playing = false);
        unawaited(ListenPlaybackService.stop());
      }
      return;
    }
    if (playing != _playing) {
      setState(() => _playing = playing);
      unawaited(_syncForegroundService(playing: playing));
    }
  }

  Future<void> _syncForegroundService({required bool playing}) async {
    final p = _payload;
    if (p == null || !_userWantsPlay) {
      if (!playing) {
        try {
          await ListenPlaybackService.stop();
        } catch (_) {}
      }
      return;
    }
    // FGS is best-effort. Never let notification/service failures break audio.
    try {
      if (playing) {
        if (ListenPlaybackService.isActive) {
          await ListenPlaybackService.update(
            title: p.title,
            artist: p.orgName,
            playing: true,
          );
        } else {
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

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
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

  Future<void> _stopMedia() async {
    _mode = _ListenMode.none;
    try {
      await _player.stop();
    } catch (_) {}
    try {
      await _whep.stop();
    } catch (_) {}
  }

  Future<void> _load() async {
    if (_loadInFlight) return;
    final now = DateTime.now();
    if (_lastLoadAt != null && now.difference(_lastLoadAt!) < _loadCooldown) {
      return;
    }
    _loadInFlight = true;
    _lastLoadAt = now;

    setState(() {
      _loading = true;
      _connecting = true;
      _error = null;
      _playing = false;
      _hasUserPaused = false;
      _userWantsPlay = true;
    });

    try {
      final api = context.read<AuthState>().api;
      try {
        await ListenPlaybackService.stop();
      } catch (_) {}
      await _stopMedia();
      final payload = await api
          .listen(widget.streamUuid)
          .timeout(const Duration(seconds: 15));
      if (!mounted) return;
      setState(() => _payload = payload);
      context.read<SelectedChannel>().select(
            uuid: payload.uuid,
            title: payload.title,
            organization: payload.orgName,
            artworkUrl: payload.artworkUrl,
            creatorType: payload.creatorType,
          );

      if (!payload.isLive) {
        setState(() {
          _loading = false;
          _connecting = false;
          _error = 'This channel is offline right now.';
        });
        unawaited(_refreshScripture());
        return;
      }

      // Show the listen chrome while connecting (not a misleading PAUSED state).
      setState(() {
        _loading = false;
        _connecting = true;
      });

      final started = await _startMedia(payload);
      if (!mounted) return;

      if (started) {
        _listenStartedAt = DateTime.now();
        setState(() {
          _playing = true;
          _connecting = false;
          _hasStartedPlayback = true;
          _hasUserPaused = false;
          _error = null;
        });
        // Start FGS after audio is up. Failure must not undo playback.
        unawaited(_syncForegroundService(playing: true));
      } else {
        setState(() {
          _connecting = false;
          _error =
              'Could not connect to the live audio. Check your connection and tap Retry.';
        });
      }

      // Presence / scripture must never block the listen UI.
      unawaited(_pingPresence());
      unawaited(_refreshScripture());
      _presenceTimer?.cancel();
      _presenceTimer = Timer.periodic(
        _presencePoll,
        (_) => unawaited(_pingPresence()),
      );
      _scriptureTimer?.cancel();
      _scriptureTimer = Timer.periodic(
        _scripturePoll,
        (_) => unawaited(_refreshScripture()),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
          _connecting = false;
        });
      }
    } on TimeoutException {
      if (mounted) {
        setState(() {
          _error = 'Timed out connecting to this stream. Tap Retry.';
          _loading = false;
          _connecting = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
          _connecting = false;
        });
      }
    } finally {
      _loadInFlight = false;
    }
  }

  /// Prefer WHEP (Opus Studio publishes). HLS is a fallback for AAC/RTMP only —
  /// Opus-in-HLS hangs or fails silently in just_audio / ExoPlayer.
  Future<bool> _startMedia(ListenPayload payload) async {
    final whepUrl = payload.whepUrl;
    var whepAttempted = false;
    if (whepUrl != null && whepUrl.isNotEmpty) {
      whepAttempted = true;
      try {
        // Set mode before start so connection callbacks update UI during connect.
        _mode = _ListenMode.whep;
        await _whep.start(whepUrl).timeout(const Duration(seconds: 25));
        // Never report success from signaling alone — require verified connect.
        if (_whep.isConnected) return true;
        throw Exception('WHEP connected without audio readiness.');
      } catch (e) {
        debugPrint('WHEP listen failed: $e');
        _mode = _ListenMode.none;
        try {
          await _whep.stop();
        } catch (_) {}
        // Fall through to HLS only when the playlist is likely AAC (not Opus).
      }
    }

    final hlsUrl = payload.hlsUrl;
    if (hlsUrl != null && hlsUrl.isNotEmpty) {
      final opusOnly = await _hlsLooksLikeOpus(hlsUrl);
      if (opusOnly) {
        // Studio WHIP → Opus HLS is not playable in ExoPlayer. Don't fake play.
        debugPrint('Skipping Opus HLS fallback after WHEP failure.');
        if (whepAttempted) return false;
        return false;
      }
      try {
        _mode = _ListenMode.hls;
        await _player.setUrl(hlsUrl).timeout(const Duration(seconds: 12));
        await _player.play().timeout(const Duration(seconds: 8));
        // just_audio can report playing while decoding nothing — require playing.
        return _player.playing;
      } catch (e) {
        debugPrint('HLS listen failed: $e');
        _mode = _ListenMode.none;
        try {
          await _player.stop();
        } catch (_) {}
      }
    }

    return false;
  }

  /// True when MediaMTX advertises Opus in the master playlist (Studio WHIP).
  Future<bool> _hlsLooksLikeOpus(String hlsUrl) async {
    try {
      final res = await http
          .get(Uri.parse(hlsUrl))
          .timeout(const Duration(seconds: 6));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        // Treat auth/network failures as non-playable when WHEP already failed.
        return true;
      }
      final body = res.body.toLowerCase();
      // MediaMTX Studio remux: CODECS="opus" (ExoPlayer cannot decode).
      return body.contains('codecs="opus"') ||
          body.contains("codecs='opus'") ||
          body.contains('opus');
    } catch (_) {
      // Unknown — refuse silent Opus-ish fallback after a WHEP attempt.
      return true;
    }
  }

  Future<void> _pingPresence() async {
    if (!mounted || !context.read<NetworkStatus>().hasLink) return;
    try {
      final data = await context
          .read<AuthState>()
          .api
          .presence(
            widget.streamUuid,
            sessionKey: _sessionKey,
          )
          .timeout(const Duration(seconds: 8));
      if (!mounted) return;
      setState(() {
        _listeners = data['listeners'] as int? ?? _listeners;
        _likes = data['likes'] as int? ?? _likes;
      });
    } catch (_) {}
  }

  Future<void> _refreshScripture() async {
    if (!mounted) return;
    final payload = _payload;
    // Skip only when we positively know this isn't a church channel.
    if (payload?.creatorType != null && !payload!.isChurch) {
      if (_scriptureEnabled || _scripture != null) {
        setState(() {
          _scriptureEnabled = false;
          _scripture = null;
        });
      }
      return;
    }
    if (!context.read<NetworkStatus>().hasLink) return;
    try {
      final result = await context
          .read<AuthState>()
          .api
          .scripture(widget.streamUuid)
          .timeout(const Duration(seconds: 8));
      if (!mounted) return;
      setState(() {
        _scriptureEnabled = result.enabled;
        _scripture = result.cue;
      });
      if (result.enabled) {
        context.read<SelectedChannel>().select(
              uuid: widget.streamUuid,
              title: payload?.title,
              organization: payload?.orgName,
              artworkUrl: payload?.artworkUrl,
              creatorType: 'church',
            );
      }
    } catch (_) {}
  }

  Future<void> _stopFromNotification() async {
    _userWantsPlay = false;
    if (_mode == _ListenMode.whep) {
      await _whep.setEnabled(false);
    } else if (_mode == _ListenMode.hls) {
      try {
        await _player.pause();
      } catch (_) {}
    }
    if (!mounted) return;
    setState(() {
      _playing = false;
      _connecting = false;
      _hasUserPaused = true;
    });
    try {
      await ListenPlaybackService.stop();
    } catch (_) {}
  }

  Future<void> _togglePlay() async {
    if (_connecting && !_playing) return;

    if (_mode == _ListenMode.whep) {
      final next = !_playing;
      _userWantsPlay = next;
      if (next) {
        setState(() {
          _connecting = true;
          _hasUserPaused = false;
          _error = null;
        });
        // If ICE already died, don't fake playing — reload (bypass cooldown).
        if (!_whep.isConnected) {
          _lastLoadAt = null;
          await _load();
          return;
        }
        try {
          await _whep.setEnabled(true);
        } catch (e) {
          if (!mounted) return;
          setState(() {
            _connecting = false;
            _error = 'Could not resume audio. Tap Retry.';
          });
          return;
        }
        if (!mounted) return;
        _listenStartedAt ??= DateTime.now();
        setState(() {
          _playing = true;
          _connecting = false;
          _hasStartedPlayback = true;
        });
        unawaited(_syncForegroundService(playing: true));
      } else {
        try {
          await _whep.setEnabled(false);
        } catch (_) {}
        if (!mounted) return;
        setState(() {
          _playing = false;
          _connecting = false;
          _hasUserPaused = true;
        });
        unawaited(_syncForegroundService(playing: false));
      }
      return;
    }

    if (_mode == _ListenMode.none) {
      await _load();
      return;
    }

    if (_playing) {
      _userWantsPlay = false;
      try {
        await _player.pause();
      } catch (_) {}
      if (!mounted) return;
      setState(() {
        _playing = false;
        _hasUserPaused = true;
      });
      unawaited(_syncForegroundService(playing: false));
    } else {
      _userWantsPlay = true;
      setState(() {
        _connecting = true;
        _hasUserPaused = false;
      });
      try {
        await _player.play();
      } catch (e) {
        if (!mounted) return;
        setState(() {
          _connecting = false;
          _error = 'Could not resume audio. Tap Retry.';
        });
        return;
      }
      if (!mounted) return;
      _listenStartedAt ??= DateTime.now();
      setState(() {
        _playing = true;
        _connecting = false;
        _hasStartedPlayback = true;
      });
      unawaited(_syncForegroundService(playing: true));
    }
  }

  Future<void> _like() async {
    final auth = context.read<AuthState>();
    final net = context.read<NetworkStatus>();
    if (!auth.isLoggedIn) {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      if (!mounted || !auth.isLoggedIn) return;
    }
    if (!net.hasLink) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('You’re offline — likes need a connection.')),
      );
      return;
    }
    try {
      final likes = await auth.api.like(widget.streamUuid);
      if (!mounted) return;
      setState(() => _likes = likes);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  String get _statusLabel {
    if (_loading || _connecting) {
      if (_mode == _ListenMode.whep &&
          _whep.connectionState == WhepConnectionState.reconnecting) {
        return 'RECONNECTING';
      }
      return 'CONNECTING';
    }
    if (_mode == _ListenMode.whep &&
        _whep.connectionState == WhepConnectionState.reconnecting) {
      return 'RECONNECTING';
    }
    if (_playing) return 'LISTENING';
    // PAUSED only after a real pause — never as the idle default.
    if (_hasUserPaused || (_hasStartedPlayback && !_userWantsPlay)) {
      return 'PAUSED';
    }
    return 'TAP PLAY';
  }

  @override
  Widget build(BuildContext context) {
    final p = _payload;
    final whepRenderer = _whep.renderer;

    return Scaffold(
      appBar: AppBar(
        title: Text(p?.title ?? 'Listen'),
        actions: const [
          Padding(
            padding: EdgeInsets.only(right: 12),
            child: NetworkPill(),
          ),
        ],
      ),
      body: Column(
        children: [
          const NetworkBanner(),
          Expanded(
            child: SafeArea(
              top: false,
              child: Stack(
                children: [
                  // Keep a tiny WebRTC view attached so remote Opus audio keeps a sink.
                  if (whepRenderer != null)
                    Positioned(
                      width: 1,
                      height: 1,
                      left: -10,
                      top: -10,
                      child: RTCVideoView(whepRenderer),
                    ),
                  Positioned.fill(
                    child: _loading && _payload == null
                        ? const Center(child: CircularProgressIndicator())
                        : _error != null && _payload == null
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        _error!,
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(color: LiveMixTheme.bad),
                                      ),
                                      const SizedBox(height: 16),
                                      OutlinedButton(
                                        onPressed: _load,
                                        child: const Text('Retry'),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            : ListView(
                                padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
                                children: [
                                  // 1) Channel header
                                  Text(
                                    p?.orgName ?? Brand.name,
                                    style: const TextStyle(
                                      color: LiveMixTheme.mute,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    p?.title ?? '',
                                    style: GoogleFonts.outfit(
                                      color: LiveMixTheme.mist,
                                      fontSize: 26,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -0.5,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: TextButton.icon(
                                      onPressed: _like,
                                      icon: const Icon(
                                        Icons.favorite_rounded,
                                        color: LiveMixTheme.gold,
                                      ),
                                      label: Text(
                                        '$_likes',
                                        style: const TextStyle(
                                          color: LiveMixTheme.mist,
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  if (_error != null) ...[
                                    Text(
                                      _error!,
                                      style: const TextStyle(
                                        color: LiveMixTheme.bad,
                                        fontSize: 13,
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                  ],
                                  // 2) Player controls (below channel name)
                                  FilledButton.icon(
                                    onPressed: (_connecting && !_playing)
                                        ? null
                                        : _togglePlay,
                                    icon: Icon(
                                      _playing
                                          ? Icons.pause_rounded
                                          : Icons.play_arrow_rounded,
                                    ),
                                    label: Text(
                                      _connecting && !_playing
                                          ? 'Connecting…'
                                          : (_playing ? 'Pause' : 'Play'),
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  SizedBox(
                                    height: 10,
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(99),
                                      child: LinearProgressIndicator(
                                        value: _playing
                                            ? _pulse.clamp(0.12, 0.95)
                                            : (_connecting ? null : 0.05),
                                        backgroundColor: LiveMixTheme.panelHi,
                                        color: _playing
                                            ? LiveMixTheme.good
                                            : LiveMixTheme.mute,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: [
                                      Text(
                                        _statusLabel,
                                        style: const TextStyle(
                                          color: LiveMixTheme.mute,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 1.1,
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      LiveDurationText(
                                        elapsed: _listened,
                                        fontSize: 22,
                                      ),
                                      const Spacer(),
                                      Text(
                                        '$_listeners listening',
                                        style: const TextStyle(
                                          color: LiveMixTheme.mute,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 18),
                                  AspectRatio(
                                    aspectRatio: 16 / 10,
                                    child: Container(
                                      decoration: BoxDecoration(
                                        color: LiveMixTheme.panel,
                                        borderRadius: BorderRadius.circular(20),
                                        gradient: p?.artworkUrl == null
                                            ? const LinearGradient(
                                                begin: Alignment.topLeft,
                                                end: Alignment.bottomRight,
                                                colors: [
                                                  Color(0xFF242933),
                                                  Color(0xFF151820),
                                                ],
                                              )
                                            : null,
                                        image: p?.artworkUrl != null
                                            ? DecorationImage(
                                                image: NetworkImage(
                                                  p!.artworkUrl!,
                                                ),
                                                fit: BoxFit.cover,
                                              )
                                            : null,
                                      ),
                                      child: p?.artworkUrl == null
                                          ? Center(
                                              child: Icon(
                                                Icons.graphic_eq_rounded,
                                                size: 64,
                                                color: LiveMixTheme.gold
                                                    .withOpacity(
                                                  0.55 + _pulse * 0.4,
                                                ),
                                              ),
                                            )
                                          : null,
                                    ),
                                  ),
                                  // 3) Scripture board (church channels)
                                  if (_scriptureEnabled) ...[
                                    const SizedBox(height: 22),
                                    _ScriptureBoard(cue: _scripture),
                                  ],
                                ],
                              ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ScriptureBoard extends StatelessWidget {
  const _ScriptureBoard({this.cue});

  final ScriptureCue? cue;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: LiveMixTheme.accent.withOpacity(0.22)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                'SCRIPTURE',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.accentBright,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const Spacer(),
              Text(
                cue?.version ?? 'KJV',
                style: const TextStyle(
                  color: LiveMixTheme.mute,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (cue == null) ...[
            Text(
              'Waiting for the next verse…',
              style: GoogleFonts.outfit(
                color: LiveMixTheme.mute,
                fontSize: 15,
                height: 1.4,
              ),
            ),
          ] else ...[
            Text(
              cue!.ref,
              style: GoogleFonts.outfit(
                color: LiveMixTheme.mist,
                fontSize: 18,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              cue!.text,
              style: GoogleFonts.outfit(
                color: LiveMixTheme.mist.withOpacity(0.92),
                fontSize: 16,
                height: 1.45,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

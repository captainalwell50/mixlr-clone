import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:just_audio/just_audio.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/network_status.dart';
import '../theme.dart';
import '../widgets/network_banner.dart';
import '../widgets/signal_meter.dart';
import 'login_screen.dart';

class ListenScreen extends StatefulWidget {
  const ListenScreen({super.key, required this.streamUuid});

  final String streamUuid;

  @override
  State<ListenScreen> createState() => _ListenScreenState();
}

class _ListenScreenState extends State<ListenScreen> {
  final _player = AudioPlayer();
  final _sessionKey = const Uuid().v4();
  ListenPayload? _payload;
  String? _error;
  bool _loading = true;
  bool _playing = false;
  int _listeners = 0;
  int _likes = 0;
  Timer? _presenceTimer;
  Timer? _listenTick;
  DateTime? _listenStartedAt;
  Duration _listened = Duration.zero;
  double _pulse = 0.2;

  @override
  void initState() {
    super.initState();
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
  void dispose() {
    _presenceTimer?.cancel();
    _listenTick?.cancel();
    _player.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<AuthState>().api;
      final payload = await api.listen(widget.streamUuid);
      if (!mounted) return;
      setState(() => _payload = payload);

      if (payload.hlsUrl != null && payload.hlsUrl!.isNotEmpty) {
        await _player.setUrl(payload.hlsUrl!);
        await _player.play();
        _listenStartedAt = DateTime.now();
        if (!mounted) return;
        setState(() {
          _playing = true;
          _loading = false; // show player as soon as audio is up
        });
      } else if (mounted) {
        setState(() => _loading = false);
      }

      // Presence must never block the listen UI.
      unawaited(_pingPresence());
      _presenceTimer?.cancel();
      _presenceTimer = Timer.periodic(
        const Duration(seconds: 20),
        (_) => unawaited(_pingPresence()),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
        });
      }
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

  Future<void> _togglePlay() async {
    if (_playing) {
      await _player.pause();
      setState(() => _playing = false);
    } else {
      await _player.play();
      _listenStartedAt ??= DateTime.now();
      setState(() => _playing = true);
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

  @override
  Widget build(BuildContext context) {
    final p = _payload;
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
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
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
                      : Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              AspectRatio(
                                aspectRatio: 1,
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: LiveMixTheme.panel,
                                    borderRadius: BorderRadius.circular(24),
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
                                            image: NetworkImage(p!.artworkUrl!),
                                            fit: BoxFit.cover,
                                          )
                                        : null,
                                  ),
                                  child: p?.artworkUrl == null
                                      ? Center(
                                          child: Icon(
                                            Icons.graphic_eq_rounded,
                                            size: 84,
                                            color: LiveMixTheme.gold
                                                .withOpacity(0.55 + _pulse * 0.4),
                                          ),
                                        )
                                      : null,
                                ),
                              ),
                              const SizedBox(height: 22),
                              Text(
                                p?.orgName ?? Brand.name,
                                style: const TextStyle(color: LiveMixTheme.mute),
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
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  const Text(
                                    'LISTENING',
                                    style: TextStyle(
                                      color: LiveMixTheme.mute,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 1.1,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  LiveDurationText(elapsed: _listened, fontSize: 22),
                                ],
                              ),
                              const Spacer(),
                              Row(
                                children: [
                                  Text(
                                    '$_listeners listening',
                                    style: const TextStyle(color: LiveMixTheme.mute),
                                  ),
                                  const Spacer(),
                                  TextButton.icon(
                                    onPressed: _like,
                                    icon: const Icon(
                                      Icons.favorite_rounded,
                                      color: LiveMixTheme.gold,
                                    ),
                                    label: Text(
                                      '$_likes',
                                      style: const TextStyle(color: LiveMixTheme.mist),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              SizedBox(
                                height: 10,
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(99),
                                  child: LinearProgressIndicator(
                                    value: _playing ? _pulse.clamp(0.12, 0.95) : 0.05,
                                    backgroundColor: LiveMixTheme.panelHi,
                                    color: _playing
                                        ? LiveMixTheme.good
                                        : LiveMixTheme.mute,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 16),
                              FilledButton.icon(
                                onPressed: _togglePlay,
                                icon: Icon(
                                  _playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                                ),
                                label: Text(_playing ? 'Pause' : 'Play'),
                              ),
                            ],
                          ),
                        ),
            ),
          ),
        ],
      ),
    );
  }
}

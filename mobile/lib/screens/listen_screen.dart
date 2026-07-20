import 'dart:async';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _presenceTimer?.cancel();
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
      setState(() => _payload = payload);

      if (payload.hlsUrl != null && payload.hlsUrl!.isNotEmpty) {
        await _player.setUrl(payload.hlsUrl!);
        await _player.play();
        setState(() => _playing = true);
      }

      await _pingPresence();
      _presenceTimer = Timer.periodic(
        const Duration(seconds: 20),
        (_) => _pingPresence(),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pingPresence() async {
    try {
      final data = await context.read<AuthState>().api.presence(
            widget.streamUuid,
            sessionKey: _sessionKey,
          );
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
    } else {
      await _player.play();
    }
    setState(() => _playing = !_playing);
  }

  Future<void> _like() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      if (!auth.isLoggedIn) return;
    }
    try {
      final likes = await auth.api.like(widget.streamUuid);
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
      appBar: AppBar(title: Text(p?.title ?? 'Listen')),
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(_error!, style: const TextStyle(color: Colors.redAccent)),
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
                              borderRadius: BorderRadius.circular(20),
                              image: p?.artworkUrl != null
                                  ? DecorationImage(
                                      image: NetworkImage(p!.artworkUrl!),
                                      fit: BoxFit.cover,
                                    )
                                  : null,
                            ),
                            child: p?.artworkUrl == null
                                ? const Center(
                                    child: Icon(
                                      Icons.graphic_eq,
                                      size: 72,
                                      color: LiveMixTheme.gold,
                                    ),
                                  )
                                : null,
                          ),
                        ),
                        const SizedBox(height: 24),
                        Text(
                          p?.orgName ?? 'Live Mix',
                          style: const TextStyle(color: LiveMixTheme.mute),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          p?.title ?? '',
                          style: const TextStyle(
                            color: LiveMixTheme.mist,
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (p?.description != null && p!.description!.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(
                            p.description!,
                            style: const TextStyle(color: LiveMixTheme.mute, height: 1.4),
                          ),
                        ],
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
                              icon: const Icon(Icons.favorite, color: LiveMixTheme.gold),
                              label: Text(
                                '$_likes',
                                style: const TextStyle(color: LiveMixTheme.mist),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: _togglePlay,
                          icon: Icon(_playing ? Icons.pause : Icons.play_arrow),
                          label: Text(_playing ? 'Pause' : 'Play'),
                        ),
                      ],
                    ),
                  ),
      ),
    );
  }
}

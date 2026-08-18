import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../services/auth_state.dart';
import '../services/listen_controller.dart';
import '../theme.dart';
import '../widgets/live_gallery_panel.dart';
import '../widgets/network_banner.dart';
import '../widgets/scripture_board.dart';
import '../widgets/signal_meter.dart';
import 'login_screen.dart';

class ListenScreen extends StatefulWidget {
  const ListenScreen({super.key, required this.streamUuid});

  final String streamUuid;

  @override
  State<ListenScreen> createState() => _ListenScreenState();
}

class _ListenScreenState extends State<ListenScreen> {
  ListenController? _listen;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final listen = context.read<ListenController>();
      _listen = listen;
      listen.attachUi();
      unawaited(listen.open(widget.streamUuid, context: context));
    });
  }

  @override
  void dispose() {
    // Detach UI only — keep audio / FGS / WHEP alive for mini-player.
    _listen?.detachUi();
    super.dispose();
  }

  Future<void> _like() async {
    final listen = context.read<ListenController>();
    await listen.like(
      context: context,
      ensureLoggedIn: () async {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
        if (!mounted) return false;
        return context.read<AuthState>().isLoggedIn;
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final listen = context.watch<ListenController>();
    final p = listen.payload;
    final whepRenderer = listen.whepRenderer;
    final showForThis =
        listen.streamUuid == widget.streamUuid || listen.streamUuid == null;

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
                  if (whepRenderer != null)
                    Positioned(
                      width: 1,
                      height: 1,
                      left: -10,
                      top: -10,
                      child: RTCVideoView(whepRenderer),
                    ),
                  Positioned.fill(
                    child: !showForThis || (listen.loading && p == null)
                        ? const Center(child: CircularProgressIndicator())
                        : listen.error != null && p == null
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        listen.error!,
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(
                                          color: LiveMixTheme.bad,
                                        ),
                                      ),
                                      const SizedBox(height: 16),
                                      OutlinedButton(
                                        onPressed: () =>
                                            listen.load(context: context),
                                        child: const Text('Retry'),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            : ListView(
                                padding: const EdgeInsets.fromLTRB(
                                  24,
                                  16,
                                  24,
                                  32,
                                ),
                                children: [
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
                                        '${listen.likes}',
                                        style: const TextStyle(
                                          color: LiveMixTheme.mist,
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  if (listen.error != null) ...[
                                    Text(
                                      listen.error!,
                                      style: const TextStyle(
                                        color: LiveMixTheme.bad,
                                        fontSize: 13,
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                  ],
                                  FilledButton.icon(
                                    onPressed:
                                        (listen.connecting && !listen.playing)
                                            ? null
                                            : () => listen.togglePlay(
                                                  context: context,
                                                ),
                                    icon: Icon(
                                      listen.playing
                                          ? Icons.pause_rounded
                                          : Icons.play_arrow_rounded,
                                    ),
                                    label: Text(
                                      listen.connecting && !listen.playing
                                          ? 'Connecting…'
                                          : (listen.playing
                                              ? 'Pause'
                                              : 'Play'),
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  SizedBox(
                                    height: 10,
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(99),
                                      child: LinearProgressIndicator(
                                        value: listen.playing
                                            ? listen.pulse.clamp(0.12, 0.95)
                                            : (listen.connecting
                                                ? null
                                                : 0.05),
                                        backgroundColor: LiveMixTheme.panelHi,
                                        color: listen.playing
                                            ? LiveMixTheme.good
                                            : LiveMixTheme.mute,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: [
                                      Text(
                                        listen.statusLabel,
                                        style: const TextStyle(
                                          color: LiveMixTheme.mute,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 1.1,
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      LiveDurationText(
                                        elapsed: listen.listened,
                                        fontSize: 22,
                                      ),
                                      const Spacer(),
                                      Text(
                                        '${listen.listeners} listening',
                                        style: const TextStyle(
                                          color: LiveMixTheme.mute,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 18),
                                  Builder(
                                    builder: (context) {
                                      final screenH =
                                          MediaQuery.sizeOf(context).height;
                                      final stageH = listen.scriptureEnabled
                                          ? (screenH * 0.46)
                                              .clamp(320.0, 520.0)
                                          : null;
                                      final stage = ClipRRect(
                                        borderRadius:
                                            BorderRadius.circular(20),
                                        child: Stack(
                                          fit: StackFit.expand,
                                          children: [
                                            DecoratedBox(
                                              decoration: BoxDecoration(
                                                color: LiveMixTheme.panel,
                                                gradient: p?.artworkUrl == null
                                                    ? const LinearGradient(
                                                        begin:
                                                            Alignment.topLeft,
                                                        end: Alignment
                                                            .bottomRight,
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
                                              child: p?.artworkUrl == null &&
                                                      !listen.scriptureEnabled
                                                  ? Center(
                                                      child: Icon(
                                                        Icons
                                                            .graphic_eq_rounded,
                                                        size: 64,
                                                        color: LiveMixTheme
                                                            .gold
                                                            .withValues(
                                                          alpha: 0.55 +
                                                              listen.pulse *
                                                                  0.4,
                                                        ),
                                                      ),
                                                    )
                                                  : null,
                                            ),
                                            if (listen.scriptureEnabled)
                                              ScriptureArtOverlay(
                                                cue: listen.scripture,
                                              ),
                                          ],
                                        ),
                                      );
                                      if (stageH != null) {
                                        return SizedBox(
                                          height: stageH,
                                          width: double.infinity,
                                          child: stage,
                                        );
                                      }
                                      return AspectRatio(
                                        aspectRatio: 16 / 10,
                                        child: stage,
                                      );
                                    },
                                  ),
                                  const SizedBox(height: 22),
                                  LiveGalleryPanel(
                                    streamUuid: widget.streamUuid,
                                    compactEmpty: true,
                                  ),
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

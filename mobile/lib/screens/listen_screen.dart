import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/auth_state.dart';
import '../services/listen_controller.dart';
import '../theme.dart';
import '../widgets/legal_links.dart';
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

  Future<void> _openGive(GivingInfo giving) async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: LiveMixTheme.panel,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(22, 16, 22, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Give online',
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mist,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (giving.note != null && giving.note!.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    giving.note!,
                    style: const TextStyle(color: LiveMixTheme.mute),
                  ),
                ],
                if (giving.hasUrl) ...[
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => openExternalUrl(giving.url!),
                    child: const Text('Open giving page'),
                  ),
                ],
                if (giving.hasAccount) ...[
                  const SizedBox(height: 16),
                  const Text(
                    'ACCOUNT DETAILS',
                    style: TextStyle(
                      color: LiveMixTheme.mute,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.1,
                    ),
                  ),
                  if (giving.accountName != null &&
                      giving.accountName!.isNotEmpty)
                    _GiveCopyRow(label: 'Name', value: giving.accountName!),
                  if (giving.bankName != null && giving.bankName!.isNotEmpty)
                    _GiveCopyRow(label: 'Bank', value: giving.bankName!),
                  if (giving.accountNumber != null &&
                      giving.accountNumber!.isNotEmpty)
                    _GiveCopyRow(
                      label: 'Account number',
                      value: giving.accountNumber!,
                    ),
                  if (giving.copyAll.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: () => Clipboard.setData(
                        ClipboardData(text: giving.copyAll),
                      ),
                      child: const Text('Copy all details'),
                    ),
                  ],
                ],
              ],
            ),
          ),
        );
      },
    );
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
                                  onPressed: () => listen.load(
                                    context: context,
                                    force: true,
                                  ),
                                  child: const Text('Retry'),
                                ),
                              ],
                            ),
                          ),
                        )
                      : ListView(
                          padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
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
                              onPressed: (listen.connecting && !listen.playing)
                                  ? null
                                  : () => listen.togglePlay(context: context),
                              icon: Icon(
                                listen.playing
                                    ? Icons.pause_rounded
                                    : Icons.play_arrow_rounded,
                              ),
                              label: Text(
                                listen.connecting && !listen.playing
                                    ? 'Connecting…'
                                    : (listen.playing ? 'Pause' : 'Play'),
                              ),
                            ),
                            const SizedBox(height: 12),
                            _ListenPulseBar(
                              playing: listen.playing,
                              connecting: listen.connecting,
                            ),
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                Flexible(
                                  child: Text(
                                    listen.statusLabel,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: LiveMixTheme.mute,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 1.1,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                _ListenElapsed(
                                  playing: listen.playing,
                                  startedAt: listen.listenStartedAt,
                                  frozen: listen.listened,
                                ),
                                const Spacer(),
                                Flexible(
                                  child: Text(
                                    '${listen.listeners} listening',
                                    overflow: TextOverflow.ellipsis,
                                    textAlign: TextAlign.end,
                                    style: const TextStyle(
                                      color: LiveMixTheme.mute,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            if (p?.giving != null) ...[
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                onPressed: () => _openGive(p!.giving!),
                                icon: const Icon(
                                  Icons.volunteer_activism_rounded,
                                ),
                                label: const Text('Give online'),
                              ),
                            ],
                            const SizedBox(height: 18),
                            _ListenStage(
                              payload: p,
                              scriptureEnabled: listen.scriptureEnabled,
                              scripture: listen.scripture,
                              song: listen.song,
                            ),
                            const SizedBox(height: 22),
                            LiveGalleryPanel(
                              key: ValueKey(widget.streamUuid),
                              streamUuid: widget.streamUuid,
                              compactEmpty: true,
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

class _ListenStage extends StatelessWidget {
  const _ListenStage({
    required this.payload,
    required this.scriptureEnabled,
    required this.scripture,
    required this.song,
  });

  final ListenPayload? payload;
  final bool scriptureEnabled;
  final ScriptureCue? scripture;
  final SongCue? song;

  @override
  Widget build(BuildContext context) {
    final screenH = MediaQuery.sizeOf(context).height;
    final stageH =
        scriptureEnabled ? (screenH * 0.46).clamp(320.0, 520.0) : null;
    final artwork = payload?.artworkUrl;
    final dpr = MediaQuery.devicePixelRatioOf(context);
    final cacheW = (MediaQuery.sizeOf(context).width * dpr).round().clamp(
          320,
          1080,
        );

    final stage = ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Stack(
        fit: StackFit.expand,
        children: [
          ColoredBox(
            color: LiveMixTheme.panel,
            child: artwork == null
                ? const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          Color(0xFF242933),
                          Color(0xFF151820),
                        ],
                      ),
                    ),
                    child: Center(
                      child: Icon(
                        Icons.graphic_eq_rounded,
                        size: 64,
                        color: Color(0x8CD4A017),
                      ),
                    ),
                  )
                : Image.network(
                    artwork,
                    fit: BoxFit.cover,
                    gaplessPlayback: true,
                    cacheWidth: cacheW,
                    errorBuilder: (_, __, ___) => const ColoredBox(
                      color: LiveMixTheme.panelHi,
                      child: Center(
                        child: Icon(
                          Icons.graphic_eq_rounded,
                          size: 64,
                          color: Color(0x8CD4A017),
                        ),
                      ),
                    ),
                  ),
          ),
          if (scriptureEnabled)
            ScriptureArtOverlay(
              cue: scripture,
              song: song,
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
  }
}

class _ListenPulseBar extends StatefulWidget {
  const _ListenPulseBar({
    required this.playing,
    required this.connecting,
  });

  final bool playing;
  final bool connecting;

  @override
  State<_ListenPulseBar> createState() => _ListenPulseBarState();
}

class _ListenPulseBarState extends State<_ListenPulseBar>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    if (widget.playing) _pulse.repeat();
  }

  @override
  void didUpdateWidget(covariant _ListenPulseBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.playing && !_pulse.isAnimating) {
      _pulse.repeat();
    } else if (!widget.playing && _pulse.isAnimating) {
      _pulse.stop();
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 10,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(99),
        child: AnimatedBuilder(
          animation: _pulse,
          builder: (context, _) {
            final value = widget.playing
                ? (0.18 + (_pulse.value * 0.7)).clamp(0.12, 0.95)
                : (widget.connecting ? null : 0.05);
            return LinearProgressIndicator(
              value: value,
              backgroundColor: LiveMixTheme.panelHi,
              color: widget.playing ? LiveMixTheme.good : LiveMixTheme.mute,
            );
          },
        ),
      ),
    );
  }
}

class _ListenElapsed extends StatefulWidget {
  const _ListenElapsed({
    required this.playing,
    required this.startedAt,
    required this.frozen,
  });

  final bool playing;
  final DateTime? startedAt;
  final Duration frozen;

  @override
  State<_ListenElapsed> createState() => _ListenElapsedState();
}

class _ListenElapsedState extends State<_ListenElapsed> {
  Timer? _tick;
  Duration _elapsed = Duration.zero;

  @override
  void initState() {
    super.initState();
    _elapsed = widget.frozen;
    _syncTicker();
  }

  @override
  void didUpdateWidget(covariant _ListenElapsed oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!widget.playing) {
      _elapsed = widget.frozen;
    }
    _syncTicker();
  }

  void _syncTicker() {
    _tick?.cancel();
    if (!widget.playing || widget.startedAt == null) return;
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {
        _elapsed = DateTime.now().difference(widget.startedAt!);
      });
    });
    _elapsed = DateTime.now().difference(widget.startedAt!);
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return LiveDurationText(
      elapsed: _elapsed,
      fontSize: 22,
    );
  }
}

class _GiveCopyRow extends StatelessWidget {
  const _GiveCopyRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: LiveMixTheme.mute,
                    fontSize: 12,
                  ),
                ),
                Text(
                  value,
                  style: const TextStyle(
                    color: LiveMixTheme.mist,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => Clipboard.setData(ClipboardData(text: value)),
            child: const Text('Copy'),
          ),
        ],
      ),
    );
  }
}

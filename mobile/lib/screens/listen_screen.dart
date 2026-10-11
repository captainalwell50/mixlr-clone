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
  final ScrollController _scrollController = ScrollController();
  bool _showStickyDock = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final listen = context.read<ListenController>();
      _listen = listen;
      listen.attachUi();
      unawaited(listen.open(widget.streamUuid, context: context));
    });
  }

  void _onScroll() {
    final show = _scrollController.hasClients && _scrollController.offset > 160;
    if (show != _showStickyDock && mounted) {
      setState(() => _showStickyDock = show);
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    // Detach UI only — keep audio / FGS / WHEP alive for mini-player.
    _listen?.detachUi();
    super.dispose();
  }

  Future<void> _promptUnlock() async {
    final controller = TextEditingController();
    String? localError;
    await showDialog<bool>(
      context: context,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: LiveMixTheme.panel,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Text(
                'Private Broadcast',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mist,
                  fontWeight: FontWeight.w700,
                ),
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'This broadcast is protected by a password. Enter the access password to start listening.',
                    style: TextStyle(color: LiveMixTheme.mute, fontSize: 13),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: controller,
                    obscureText: true,
                    autofocus: true,
                    decoration: InputDecoration(
                      hintText: 'Enter password',
                      errorText: localError,
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogCtx, false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () async {
                    final pwd = controller.text.trim();
                    if (pwd.isEmpty) {
                      setDialogState(() => localError = 'Password required');
                      return;
                    }
                    final ok = await this.context.read<ListenController>().unlockWithPassword(pwd, context: this.context);
                    if (ok) {
                      if (dialogCtx.mounted) Navigator.pop(dialogCtx, true);
                    } else {
                      setDialogState(() {
                        localError = this.context.read<ListenController>().error ?? 'Incorrect password';
                      });
                    }
                  },
                  child: const Text('Unlock & Listen'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  Future<void> _openChat() async {
    final streamUuid = widget.streamUuid;
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: LiveMixTheme.panel,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return _LiveChatSheet(streamUuid: streamUuid);
      },
    );
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

  Future<bool> _ensureLoggedIn() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
    if (!mounted) return false;
    return context.read<AuthState>().isLoggedIn;
  }

  Future<void> _like() async {
    final listen = context.read<ListenController>();
    await listen.like(
      context: context,
      ensureLoggedIn: _ensureLoggedIn,
    );
  }

  Future<void> _toggleFollow() async {
    final listen = context.read<ListenController>();
    await listen.toggleFollow(
      context,
      onRequireLogin: _ensureLoggedIn,
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
        actions: [
          if (p?.chatEnabled ?? true)
            IconButton(
              tooltip: 'Live Chat',
              icon: const Icon(Icons.chat_bubble_outline_rounded, color: LiveMixTheme.mist),
              onPressed: _openChat,
            ),
          const Padding(
            padding: EdgeInsets.only(right: 12),
            child: NetworkPill(),
          ),
        ],
      ),
      bottomNavigationBar: (_showStickyDock && p != null && !p.isPasswordRequired)
          ? _StickyTransportDock(
              payload: p,
              playing: listen.playing,
              connecting: listen.connecting,
              statusLabel: listen.statusLabel,
              onTogglePlay: () => listen.togglePlay(context: context),
              onOpenChat: (p.chatEnabled) ? _openChat : null,
            )
          : null,
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
                      : (p != null && p.isPasswordRequired)
                          ? PrivateUnlockView(
                              payload: p,
                              loading: listen.loading,
                              errorMessage: listen.error,
                              onUnlock: (password) => listen.unlockWithPassword(
                                password,
                                context: context,
                              ),
                            )
                          : ListView(
                          controller: _scrollController,
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
                            Row(
                              children: [
                                TextButton.icon(
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
                                const SizedBox(width: 8),
                                if (p != null)
                                  _FollowPill(
                                    following: listen.isFollowing,
                                    onPressed: _toggleFollow,
                                  ),
                              ],
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
                              FilledButton.icon(
                                style: FilledButton.styleFrom(
                                  backgroundColor: LiveMixTheme.live,
                                  foregroundColor: Colors.white,
                                  disabledBackgroundColor: LiveMixTheme.live,
                                  disabledForegroundColor: Colors.white,
                                ),
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
                              ndi: listen.ndi,
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
    this.ndi,
  });

  final ListenPayload? payload;
  final bool scriptureEnabled;
  final ScriptureCue? scripture;
  final SongCue? song;
  final NdiCue? ndi;

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
              ndi: ndi,
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

class _StickyTransportDock extends StatelessWidget {
  const _StickyTransportDock({
    required this.payload,
    required this.playing,
    required this.connecting,
    required this.statusLabel,
    required this.onTogglePlay,
    this.onOpenChat,
  });

  final ListenPayload payload;
  final bool playing;
  final bool connecting;
  final String statusLabel;
  final VoidCallback onTogglePlay;
  final VoidCallback? onOpenChat;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(16, 10, 16, 10 + MediaQuery.paddingOf(context).bottom),
      decoration: BoxDecoration(
        color: const Color(0xFF16201C),
        border: const Border(
          top: BorderSide(color: Color(0x26FFFFFF)),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.4),
            blurRadius: 12,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton.filled(
            style: IconButton.styleFrom(
              backgroundColor: LiveMixTheme.accent,
              foregroundColor: LiveMixTheme.ink,
              minimumSize: const Size(42, 42),
            ),
            onPressed: (connecting && !playing) ? null : onTogglePlay,
            icon: Icon(
              playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
              size: 24,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  payload.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mist,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
                Text(
                  statusLabel,
                  style: const TextStyle(
                    color: LiveMixTheme.mute,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                  ),
                ),
              ],
            ),
          ),
          if (onOpenChat != null)
            IconButton(
              tooltip: 'Live Chat',
              icon: const Icon(Icons.forum_rounded, color: LiveMixTheme.accentBright, size: 22),
              onPressed: onOpenChat,
            ),
        ],
      ),
    );
  }
}

class _LiveChatSheet extends StatefulWidget {
  const _LiveChatSheet({required this.streamUuid});
  final String streamUuid;

  @override
  State<_LiveChatSheet> createState() => _LiveChatSheetState();
}

class _LiveChatSheetState extends State<_LiveChatSheet> {
  final List<ChatMessageItem> _messages = [];
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  Timer? _pollTimer;
  bool _loading = true;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _fetch();
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) => _fetch());
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _fetch() async {
    if (!mounted) return;
    try {
      final api = context.read<AuthState>().api;
      final res = await api.fetchChat(widget.streamUuid);
      if (!mounted) return;
      setState(() {
        _loading = false;
        _messages.clear();
        _messages.addAll(res.messages);
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send() async {
    final text = _textController.text.trim();
    if (text.isEmpty || _sending) return;
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      if (!mounted || !context.read<AuthState>().isLoggedIn) return;
    }
    setState(() => _sending = true);
    try {
      final api = context.read<AuthState>().api;
      final msg = await api.sendChat(widget.streamUuid, text);
      _textController.clear();
      if (!mounted) return;
      setState(() {
        _sending = false;
        _messages.add(msg);
      });
      await Future.delayed(const Duration(milliseconds: 100));
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    } catch (_) {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.75,
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Color(0x26FFFFFF))),
              ),
              child: Row(
                children: [
                  const Icon(Icons.forum_rounded, color: LiveMixTheme.accentBright, size: 20),
                  const SizedBox(width: 10),
                  Text(
                    'Live Chat',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: LiveMixTheme.mute),
                  ),
                ],
              ),
            ),
            Expanded(
              child: _loading && _messages.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : _messages.isEmpty
                      ? const Center(
                          child: Text(
                            'No messages yet. Be the first to say hello!',
                            style: TextStyle(color: LiveMixTheme.mute),
                          ),
                        )
                      : ListView.builder(
                          controller: _scrollController,
                          padding: const EdgeInsets.all(16),
                          itemCount: _messages.length,
                          itemBuilder: (context, i) {
                            final m = _messages[i];
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  CircleAvatar(
                                    radius: 14,
                                    backgroundColor: LiveMixTheme.panelHi,
                                    child: Text(
                                      m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                                      style: const TextStyle(
                                        color: LiveMixTheme.accentBright,
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          m.name,
                                          style: const TextStyle(
                                            color: LiveMixTheme.mist,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 13,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          m.body,
                                          style: const TextStyle(
                                            color: Color(0xFFD8E2DC),
                                            fontSize: 14,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              decoration: const BoxDecoration(
                color: LiveMixTheme.panel,
                border: Border(top: BorderSide(color: Color(0x26FFFFFF))),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _textController,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(
                        hintText: auth.isLoggedIn ? 'Send a message…' : 'Sign in to join chat',
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: _sending
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.send_rounded, size: 18),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FollowPill extends StatelessWidget {
  const _FollowPill({required this.following, required this.onPressed});

  final bool following;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(20),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: following
              ? LiveMixTheme.accent.withValues(alpha: 0.15)
              : LiveMixTheme.panel,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: following
                ? LiveMixTheme.accent.withValues(alpha: 0.4)
                : LiveMixTheme.line,
            width: 1,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              following
                  ? Icons.notifications_active_rounded
                  : Icons.notifications_none_rounded,
              size: 15,
              color: following ? LiveMixTheme.accent : LiveMixTheme.mute,
            ),
            const SizedBox(width: 5),
            Text(
              following ? 'Following' : 'Follow',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: following ? LiveMixTheme.accent : LiveMixTheme.mist,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class PrivateUnlockView extends StatefulWidget {
  const PrivateUnlockView({
    super.key,
    required this.payload,
    required this.loading,
    this.errorMessage,
    required this.onUnlock,
  });

  final ListenPayload payload;
  final bool loading;
  final String? errorMessage;
  final Future<bool> Function(String password) onUnlock;

  @override
  State<PrivateUnlockView> createState() => _PrivateUnlockViewState();
}

class _PrivateUnlockViewState extends State<PrivateUnlockView> {
  final TextEditingController _passwordController = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final pwd = _passwordController.text.trim();
    if (pwd.isEmpty) return;
    await widget.onUnlock(pwd);
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 32),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: LiveMixTheme.panelHi,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: LiveMixTheme.accent.withValues(alpha: 0.3),
                      width: 1.5,
                    ),
                  ),
                  child: const Icon(
                    Icons.lock_rounded,
                    size: 34,
                    color: LiveMixTheme.accentBright,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                widget.payload.orgName ?? Brand.name,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: LiveMixTheme.mute,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.3,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                widget.payload.title.isNotEmpty ? widget.payload.title : 'Private broadcast',
                textAlign: TextAlign.center,
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mist,
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'This broadcast requires an access password.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: LiveMixTheme.mute,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 28),
              TextField(
                controller: _passwordController,
                obscureText: _obscure,
                autofocus: true,
                textInputAction: TextInputAction.go,
                onSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  hintText: 'Enter password',
                  prefixIcon: const Icon(Icons.key_rounded, size: 20, color: LiveMixTheme.mute),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                      size: 20,
                      color: LiveMixTheme.mute,
                    ),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
              ),
              if (widget.errorMessage != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: LiveMixTheme.bad.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: LiveMixTheme.bad.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline_rounded, size: 18, color: LiveMixTheme.bad),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          widget.errorMessage!,
                          style: const TextStyle(
                            color: LiveMixTheme.bad,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 20),
              FilledButton(
                onPressed: widget.loading ? null : _submit,
                child: widget.loading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: LiveMixTheme.ink,
                        ),
                      )
                    : const Text('Unlock & Listen'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}


import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/selected_channel.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/scripture_board.dart';
import 'listen_screen.dart';

/// Dedicated Scripture board screen (not in bottom nav). Polls the same public
/// listen scripture API used during live listen. Does not replace the in-listen
/// scripture overlay.
class ScriptureScreen extends StatefulWidget {
  const ScriptureScreen({super.key});

  @override
  State<ScriptureScreen> createState() => _ScriptureScreenState();
}

class _ScriptureScreenState extends State<ScriptureScreen> {
  List<DiscoverCard> _live = const [];
  bool _enabled = false;
  ScriptureCue? _cue;
  bool _loading = false;
  String? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_bootstrap());
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    await _loadLiveRooms();
    if (!mounted) return;
    final selected = context.read<SelectedChannel>();
    if (!selected.hasSelection && _live.isNotEmpty) {
      final first = _live.first;
      selected.select(
        uuid: first.uuid,
        title: first.title,
        organization: first.organization,
        artworkUrl: first.artworkUrl,
        creatorType: first.creatorType,
      );
    }
    await _refreshScripture();
    if (!mounted) return;
    _poll?.cancel();
    _poll = Timer.periodic(const Duration(seconds: 3), (_) {
      unawaited(_refreshScripture(silent: true));
    });
  }

  Future<void> _loadLiveRooms() async {
    try {
      final streams = await context.read<AuthState>().api.discover();
      if (!mounted) return;
      setState(() => _live = streams);
    } catch (_) {}
  }

  Future<void> _refreshScripture({bool silent = false}) async {
    final selected = context.read<SelectedChannel>();
    final uuid = selected.uuid;
    if (uuid == null || uuid.isEmpty) {
      if (!silent && mounted) {
        setState(() {
          _enabled = false;
          _cue = null;
          _loading = false;
          _error = null;
        });
      }
      return;
    }

    if (!silent && mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final result = await context.read<AuthState>().api.scripture(uuid);
      if (!mounted) return;
      setState(() {
        _enabled = result.enabled;
        _cue = result.cue;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        if (!silent) _error = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        if (!silent) _error = e.toString();
      });
    }
  }

  void _pickChannel(DiscoverCard card) {
    context.read<SelectedChannel>().select(
          uuid: card.uuid,
          title: card.title,
          organization: card.organization,
          artworkUrl: card.artworkUrl,
          creatorType: card.creatorType,
        );
    unawaited(_refreshScripture());
  }

  @override
  Widget build(BuildContext context) {
    final selected = context.watch<SelectedChannel>();

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: const BrandMark(size: 32, compact: true),
        actions: [
          if (selected.hasSelection)
            IconButton(
              tooltip: 'Open listen',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ListenScreen(streamUuid: selected.uuid!),
                  ),
                );
              },
              icon: const Icon(Icons.headphones_rounded),
            ),
          IconButton(
            tooltip: 'Refresh',
            onPressed: () async {
              await _loadLiveRooms();
              await _refreshScripture();
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator(
        color: LiveMixTheme.accent,
        onRefresh: () async {
          await _loadLiveRooms();
          await _refreshScripture();
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
          children: [
            Text(
              'Scripture',
              style: GoogleFonts.outfit(
                color: LiveMixTheme.mist,
                fontSize: 26,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.6,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              selected.hasSelection
                  ? (selected.organization ?? selected.title ?? Brand.name)
                  : 'Live scripture board for church gatherings.',
              style: const TextStyle(color: LiveMixTheme.mute),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: LiveMixTheme.panel,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: LiveMixTheme.accent.withOpacity(0.2)),
              ),
              child: Text(
                'When a church channel posts a verse, it appears here and on the '
                'listen screen board. Open Listen to hear the stream with scripture overlay.',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mute,
                  fontSize: 13.5,
                  height: 1.4,
                ),
              ),
            ),
            if (_live.isNotEmpty) ...[
              const SizedBox(height: 14),
              SizedBox(
                height: 40,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: _live.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (context, i) {
                    final card = _live[i];
                    final active = card.uuid == selected.uuid;
                    return ChoiceChip(
                      label: Text(card.organization ?? card.title),
                      selected: active,
                      onSelected: (_) => _pickChannel(card),
                      selectedColor: LiveMixTheme.accentSoft,
                      labelStyle: TextStyle(
                        color: active
                            ? LiveMixTheme.accentBright
                            : LiveMixTheme.mist,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                      backgroundColor: LiveMixTheme.panel,
                      side: BorderSide(
                        color: active
                            ? LiveMixTheme.accent.withOpacity(0.55)
                            : LiveMixTheme.mute.withOpacity(0.25),
                      ),
                    );
                  },
                ),
              ),
            ],
            const SizedBox(height: 18),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(
                  _error!,
                  style: const TextStyle(color: LiveMixTheme.warn),
                ),
              ),
            if (_loading && _cue == null && !_enabled)
              const Padding(
                padding: EdgeInsets.only(top: 80),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (!selected.hasSelection)
              _EmptyState(
                icon: Icons.menu_book_outlined,
                title: 'No channel selected',
                body: 'Open a live room from Listen, or choose one above.',
              )
            else if (!_enabled)
              _EmptyState(
                icon: Icons.menu_book_outlined,
                title: 'Scripture not active',
                body:
                    'This channel has not enabled the live scripture board, or nothing is posted yet.',
              )
            else
              ScriptureBoardCard(cue: _cue),
            if (selected.hasSelection) ...[
              const SizedBox(height: 18),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ListenScreen(streamUuid: selected.uuid!),
                    ),
                  );
                },
                icon: const Icon(Icons.headphones_rounded),
                label: const Text('Open listen with overlay'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(icon, size: 48, color: LiveMixTheme.accent.withOpacity(0.85)),
          const SizedBox(height: 14),
          Text(
            title,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontWeight: FontWeight.w700,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            textAlign: TextAlign.center,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mute,
              fontSize: 14,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

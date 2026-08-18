import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/cache_store.dart';
import '../services/network_status.dart';
import '../services/selected_channel.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/network_banner.dart';
import 'listen_screen.dart';

class DiscoverScreen extends StatefulWidget {
  const DiscoverScreen({super.key});

  @override
  State<DiscoverScreen> createState() => _DiscoverScreenState();
}

class _DiscoverScreenState extends State<DiscoverScreen> {
  final _cache = CacheStore();
  final _searchController = TextEditingController();
  final _searchFocus = FocusNode();
  List<DiscoverCard> _streams = [];
  DateTime? _cachedAt;
  bool _loading = true;
  bool _fromCache = false;
  bool _searchOpen = false;
  String _query = '';
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final cached = await _cache.loadDiscover();
    if (cached.streams.isNotEmpty && mounted) {
      setState(() {
        _streams = cached.streams;
        _cachedAt = cached.savedAt;
        _fromCache = true;
        _loading = false;
      });
    }
    await _refresh();
  }

  Future<void> _refresh() async {
    final net = context.read<NetworkStatus>();
    setState(() {
      _error = null;
      if (_streams.isEmpty) _loading = true;
    });

    try {
      final streams = await context.read<AuthState>().api.discover();
      await _cache.saveDiscover(streams);
      if (!mounted) return;
      setState(() {
        _streams = streams;
        _cachedAt = DateTime.now();
        _fromCache = false;
        _loading = false;
      });
      await net.refresh();
    } catch (e) {
      if (!mounted) return;
      final cached = await _cache.loadDiscover();
      setState(() {
        if (cached.streams.isNotEmpty) {
          _streams = cached.streams;
          _cachedAt = cached.savedAt;
          _fromCache = true;
          _error = net.hasLink
              ? 'Couldn’t refresh — showing saved rooms'
              : 'Offline — showing saved rooms';
        } else {
          _error = e is ApiException ? e.message : e.toString();
        }
        _loading = false;
      });
    }
  }

  List<DiscoverCard> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return _streams;
    return _streams.where((card) {
      final title = card.title.toLowerCase();
      final org = (card.organization ?? '').toLowerCase();
      return title.contains(q) || org.contains(q);
    }).toList();
  }

  void _openSearch() {
    setState(() => _searchOpen = true);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _searchFocus.requestFocus();
    });
  }

  void _closeSearch() {
    setState(() {
      _searchOpen = false;
      _query = '';
      _searchController.clear();
    });
  }

  void _openRoom(DiscoverCard card) {
    context.read<SelectedChannel>().select(
          uuid: card.uuid,
          title: card.title,
          organization: card.organization,
          artworkUrl: card.artworkUrl,
          creatorType: card.creatorType,
        );
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ListenScreen(streamUuid: card.uuid),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final visible = _filtered;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: _searchOpen
            ? TextField(
                controller: _searchController,
                focusNode: _searchFocus,
                autofocus: true,
                style: const TextStyle(color: LiveMixTheme.mist),
                cursorColor: LiveMixTheme.accent,
                decoration: const InputDecoration(
                  hintText: 'Search creators…',
                  hintStyle: TextStyle(color: LiveMixTheme.mute),
                  border: InputBorder.none,
                  filled: false,
                  isDense: true,
                  contentPadding: EdgeInsets.zero,
                ),
                textInputAction: TextInputAction.search,
                onChanged: (v) => setState(() => _query = v),
              )
            : const BrandMark(size: 32, compact: true),
        actions: [
          if (_searchOpen)
            IconButton(
              tooltip: 'Close search',
              onPressed: _closeSearch,
              icon: const Icon(Icons.close_rounded),
            )
          else
            IconButton(
              tooltip: 'Search creators',
              onPressed: _openSearch,
              icon: const Icon(Icons.search_rounded),
            ),
          const Padding(
            padding: EdgeInsets.only(right: 8),
            child: Center(child: NetworkPill()),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: LiveMixTheme.gold,
        onRefresh: _refresh,
        child: _loading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 140),
                  Center(child: CircularProgressIndicator()),
                ],
              )
            : ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
                children: [
                  Text(
                    _searchOpen && _query.trim().isNotEmpty
                        ? 'Search results'
                        : 'Live now',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.6,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _searchOpen && _query.trim().isNotEmpty
                        ? (visible.isEmpty
                            ? 'No creators match “${_query.trim()}”'
                            : '${visible.length} match${visible.length == 1 ? '' : 'es'}')
                        : (_fromCache && _cachedAt != null
                            ? 'Saved ${_relative(_cachedAt!)} · pull to refresh'
                            : Brand.tagline),
                    style: const TextStyle(color: LiveMixTheme.mute),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: const TextStyle(color: LiveMixTheme.warn),
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (visible.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(28),
                      decoration: BoxDecoration(
                        color: LiveMixTheme.panel,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Column(
                        children: [
                          Icon(
                            _searchOpen && _query.trim().isNotEmpty
                                ? Icons.search_off_rounded
                                : Icons.radar_rounded,
                            size: 48,
                            color: LiveMixTheme.gold.withValues(alpha: 0.8),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            _searchOpen && _query.trim().isNotEmpty
                                ? 'No matching creators'
                                : 'No live rooms right now',
                            style: GoogleFonts.outfit(
                              color: LiveMixTheme.mist,
                              fontWeight: FontWeight.w700,
                              fontSize: 17,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _searchOpen && _query.trim().isNotEmpty
                                ? 'Try another name, or clear search to see who’s live.'
                                : 'When a creator goes on air, they’ll show up here.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: LiveMixTheme.mute,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    ...visible.map(
                      (card) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _StreamTile(
                          card: card,
                          onTap: () => _openRoom(card),
                        ),
                      ),
                    ),
                ],
              ),
      ),
    );
  }

  String _relative(DateTime at) {
    final d = DateTime.now().difference(at);
    if (d.inMinutes < 1) return 'just now';
    if (d.inMinutes < 60) return '${d.inMinutes}m ago';
    if (d.inHours < 24) return '${d.inHours}h ago';
    return '${d.inDays}d ago';
  }
}

class _StreamTile extends StatelessWidget {
  const _StreamTile({required this.card, required this.onTap});

  final DiscoverCard card;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: LiveMixTheme.panel,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: LiveMixTheme.ink,
                  borderRadius: BorderRadius.circular(12),
                  image: card.artworkUrl != null
                      ? DecorationImage(
                          image: NetworkImage(card.artworkUrl!),
                          fit: BoxFit.cover,
                        )
                      : null,
                ),
                child: card.artworkUrl == null
                    ? const Icon(
                        Icons.graphic_eq_rounded,
                        color: LiveMixTheme.gold,
                      )
                    : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      card.title,
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.mist,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      card.organization ?? Brand.name,
                      style: const TextStyle(color: LiveMixTheme.mute),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                decoration: BoxDecoration(
                  color: LiveMixTheme.liveSoft,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'LIVE',
                  style: TextStyle(
                    color: LiveMixTheme.live,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

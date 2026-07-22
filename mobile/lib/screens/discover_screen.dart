import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/cache_store.dart';
import '../services/network_status.dart';
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
  List<DiscoverCard> _streams = [];
  DateTime? _cachedAt;
  bool _loading = true;
  bool _fromCache = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: const BrandMark(size: 32, compact: true),
        actions: const [
          Padding(
            padding: EdgeInsets.only(right: 12),
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
                    'Live now',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.6,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _fromCache && _cachedAt != null
                        ? 'Saved ${_relative(_cachedAt!)} · pull to refresh'
                        : Brand.tagline,
                    style: const TextStyle(color: LiveMixTheme.mute),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: const TextStyle(color: LiveMixTheme.warn)),
                  ],
                  const SizedBox(height: 18),
                  if (_streams.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(28),
                      decoration: BoxDecoration(
                        color: LiveMixTheme.panel,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Column(
                        children: [
                          Icon(
                            Icons.radar_rounded,
                            size: 48,
                            color: LiveMixTheme.gold.withOpacity(0.8),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            'No live rooms right now',
                            style: GoogleFonts.outfit(
                              color: LiveMixTheme.mist,
                              fontWeight: FontWeight.w700,
                              fontSize: 17,
                            ),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'When a creator goes on air, they’ll show up here.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
                          ),
                        ],
                      ),
                    )
                  else
                    ..._streams.map((card) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _StreamTile(card: card),
                        )),
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
  const _StreamTile({required this.card});

  final DiscoverCard card;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: LiveMixTheme.panel,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ListenScreen(streamUuid: card.uuid),
            ),
          );
        },
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
                    ? const Icon(Icons.graphic_eq_rounded, color: LiveMixTheme.gold)
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
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
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

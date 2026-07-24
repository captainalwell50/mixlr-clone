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
import 'listen_screen.dart';

class GalleryScreen extends StatefulWidget {
  const GalleryScreen({super.key});

  @override
  State<GalleryScreen> createState() => _GalleryScreenState();
}

class _GalleryScreenState extends State<GalleryScreen> {
  List<GalleryItem> _items = const [];
  List<DiscoverCard> _live = const [];
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
      );
    }
    await _refreshGallery();
    if (!mounted) return;
    _poll?.cancel();
    _poll = Timer.periodic(const Duration(seconds: 20), (_) {
      unawaited(_refreshGallery(silent: true));
    });
  }

  Future<void> _loadLiveRooms() async {
    try {
      final streams = await context.read<AuthState>().api.discover();
      if (!mounted) return;
      setState(() => _live = streams);
    } catch (_) {}
  }

  Future<void> _refreshGallery({bool silent = false}) async {
    final selected = context.read<SelectedChannel>();
    final uuid = selected.uuid;
    if (uuid == null || uuid.isEmpty) {
      if (!silent && mounted) {
        setState(() {
          _items = const [];
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
      final items = await context.read<AuthState>().api.gallery(uuid);
      if (!mounted) return;
      setState(() {
        _items = items;
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
        );
    unawaited(_refreshGallery());
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
              await _refreshGallery();
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator(
              color: LiveMixTheme.gold,
              onRefresh: () async {
                await _loadLiveRooms();
                await _refreshGallery();
              },
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                children: [
                  Text(
                    'Live Gallery',
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
                        : 'Pick a live channel to view photos and reels.',
                    style: const TextStyle(color: LiveMixTheme.mute),
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
                  if (_loading && _items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 80),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (!selected.hasSelection)
                    _EmptyState(
                      icon: Icons.photo_library_outlined,
                      title: 'No channel selected',
                      body: 'Open a live room from Listen, or choose one above.',
                    )
                  else if (_items.isEmpty)
                    _EmptyState(
                      icon: Icons.collections_outlined,
                      title: 'Gallery is empty',
                      body:
                          'Photos and short reels posted by the studio will appear here.',
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _items.length,
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 10,
                        crossAxisSpacing: 10,
                        childAspectRatio: 0.92,
                      ),
                      itemBuilder: (context, i) {
                        final item = _items[i];
                        return _GalleryTile(
                          item: item,
                          onTap: () => _openLightbox(context, i),
                        );
                      },
                    ),
                ],
              ),
      ),
    );
  }

  void _openLightbox(BuildContext context, int index) {
    showDialog<void>(
      context: context,
      builder: (_) => _GalleryLightbox(items: _items, initialIndex: index),
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
      margin: const EdgeInsets.only(top: 24),
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(icon, size: 48, color: LiveMixTheme.gold.withOpacity(0.85)),
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
            style: const TextStyle(color: LiveMixTheme.mute, height: 1.4),
          ),
        ],
      ),
    );
  }
}

class _GalleryTile extends StatelessWidget {
  const _GalleryTile({required this.item, required this.onTap});

  final GalleryItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final thumb = item.isVideo ? (item.posterUrl ?? item.url) : item.url;
    return Material(
      color: LiveMixTheme.panel,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Stack(
          fit: StackFit.expand,
          children: [
            Image.network(
              thumb,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const ColoredBox(
                color: LiveMixTheme.panelHi,
                child: Icon(Icons.broken_image_outlined, color: LiveMixTheme.mute),
              ),
            ),
            if (item.isVideo)
              Align(
                alignment: Alignment.topLeft,
                child: Container(
                  margin: const EdgeInsets.all(8),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    item.durationSeconds != null
                        ? 'REEL · ${item.durationSeconds}s'
                        : 'REEL',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.4,
                    ),
                  ),
                ),
              ),
            if (item.caption != null && item.caption!.isNotEmpty)
              Align(
                alignment: Alignment.bottomCenter,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(10, 18, 10, 8),
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black87],
                    ),
                  ),
                  child: Text(
                    item.caption!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      height: 1.25,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _GalleryLightbox extends StatefulWidget {
  const _GalleryLightbox({
    required this.items,
    required this.initialIndex,
  });

  final List<GalleryItem> items;
  final int initialIndex;

  @override
  State<_GalleryLightbox> createState() => _GalleryLightboxState();
}

class _GalleryLightboxState extends State<_GalleryLightbox> {
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
  }

  GalleryItem get _item => widget.items[_index];

  @override
  Widget build(BuildContext context) {
    final item = _item;
    return Dialog(
      backgroundColor: Colors.black,
      insetPadding: const EdgeInsets.all(12),
      child: Stack(
        children: [
          Positioned.fill(
            child: item.isVideo
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.play_circle_outline_rounded,
                            color: Colors.white70,
                            size: 64,
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Open reel in browser for full playback',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.outfit(color: Colors.white70),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            item.url,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white54,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                : InteractiveViewer(
                    child: Center(
                      child: Image.network(
                        item.url,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const Icon(
                          Icons.broken_image_outlined,
                          color: Colors.white54,
                          size: 48,
                        ),
                      ),
                    ),
                  ),
          ),
          Positioned(
            top: 8,
            right: 8,
            child: IconButton(
              onPressed: () => Navigator.of(context).pop(),
              icon: const Icon(Icons.close_rounded, color: Colors.white),
            ),
          ),
          if (widget.items.length > 1) ...[
            Positioned(
              left: 4,
              top: 0,
              bottom: 0,
              child: IconButton(
                onPressed: () {
                  setState(() {
                    _index = (_index - 1 + widget.items.length) %
                        widget.items.length;
                  });
                },
                icon: const Icon(Icons.chevron_left_rounded, color: Colors.white),
              ),
            ),
            Positioned(
              right: 4,
              top: 0,
              bottom: 0,
              child: IconButton(
                onPressed: () {
                  setState(() {
                    _index = (_index + 1) % widget.items.length;
                  });
                },
                icon:
                    const Icon(Icons.chevron_right_rounded, color: Colors.white),
              ),
            ),
          ],
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Text(
              [
                if (item.caption != null && item.caption!.isNotEmpty)
                  item.caption!,
                '${_index + 1} / ${widget.items.length}',
              ].join('\n'),
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

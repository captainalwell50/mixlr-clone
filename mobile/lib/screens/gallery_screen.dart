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
import '../widgets/live_gallery_panel.dart';
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
        creatorType: first.creatorType,
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
          creatorType: card.creatorType,
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
                            ? LiveMixTheme.accent.withValues(alpha: 0.55)
                            : LiveMixTheme.mute.withValues(alpha: 0.25),
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
              const GalleryEmptyState(
                icon: Icons.photo_library_outlined,
                title: 'No channel selected',
                body: 'Open a live room from Listen, or choose one above.',
              )
            else if (_items.isEmpty)
              const GalleryEmptyState(
                icon: Icons.collections_outlined,
                title: 'Gallery is empty',
                body:
                    'Photos and short reels posted by the studio will appear here.',
              )
            else
              GalleryGrid(
                items: _items,
                onOpen: (i) => openGalleryLightbox(context, _items, i),
              ),
          ],
        ),
      ),
    );
  }
}

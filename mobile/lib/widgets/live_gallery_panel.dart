import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';

/// Embedded live-gallery strip for a single channel (listen room + gallery tab).
class LiveGalleryPanel extends StatefulWidget {
  const LiveGalleryPanel({
    super.key,
    required this.streamUuid,
    this.title = 'Live gallery',
    this.compactEmpty = false,
  });

  final String streamUuid;
  final String title;
  final bool compactEmpty;

  @override
  State<LiveGalleryPanel> createState() => _LiveGalleryPanelState();
}

class _LiveGalleryPanelState extends State<LiveGalleryPanel> {
  List<GalleryItem> _items = const [];
  bool _loading = true;
  String? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    unawaited(_refresh());
    _poll = Timer.periodic(const Duration(seconds: 20), (_) {
      unawaited(_refresh(silent: true));
    });
  }

  @override
  void didUpdateWidget(covariant LiveGalleryPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.streamUuid != widget.streamUuid) {
      unawaited(_refresh());
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _refresh({bool silent = false}) async {
    final uuid = widget.streamUuid;
    if (uuid.isEmpty) {
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

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.title,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mist,
            fontSize: 18,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.3,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Photos and reels from this channel',
          style: TextStyle(color: LiveMixTheme.mute, fontSize: 13),
        ),
        const SizedBox(height: 14),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              _error!,
              style: const TextStyle(color: LiveMixTheme.warn, fontSize: 13),
            ),
          ),
        if (_loading && _items.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 28),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_items.isEmpty)
          GalleryEmptyState(
            compact: widget.compactEmpty,
            icon: Icons.collections_outlined,
            title: 'No gallery yet',
            body:
                'Photos and short reels posted by the studio will appear here while you listen.',
          )
        else
          GalleryGrid(
            items: _items,
            onOpen: (index) => openGalleryLightbox(context, _items, index),
          ),
      ],
    );
  }
}

class GalleryEmptyState extends StatelessWidget {
  const GalleryEmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.compact = false,
  });

  final IconData icon;
  final String title;
  final String body;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: EdgeInsets.only(top: compact ? 4 : 8),
      padding: EdgeInsets.all(compact ? 20 : 28),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: LiveMixTheme.mute.withValues(alpha: 0.12)),
      ),
      child: Column(
        children: [
          Icon(
            icon,
            size: compact ? 36 : 48,
            color: LiveMixTheme.gold.withValues(alpha: 0.85),
          ),
          SizedBox(height: compact ? 10 : 14),
          Text(
            title,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontWeight: FontWeight.w700,
              fontSize: compact ? 15 : 17,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: LiveMixTheme.mute,
              height: 1.4,
              fontSize: compact ? 13 : 14,
            ),
          ),
        ],
      ),
    );
  }
}

class GalleryGrid extends StatelessWidget {
  const GalleryGrid({
    super.key,
    required this.items,
    required this.onOpen,
  });

  final List<GalleryItem> items;
  final ValueChanged<int> onOpen;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: items.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.92,
      ),
      itemBuilder: (context, i) {
        return GalleryTile(
          item: items[i],
          onTap: () => onOpen(i),
        );
      },
    );
  }
}

class GalleryTile extends StatelessWidget {
  const GalleryTile({super.key, required this.item, required this.onTap});

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
                child: Icon(
                  Icons.broken_image_outlined,
                  color: LiveMixTheme.mute,
                ),
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

void openGalleryLightbox(
  BuildContext context,
  List<GalleryItem> items,
  int index,
) {
  showDialog<void>(
    context: context,
    builder: (_) => GalleryLightbox(items: items, initialIndex: index),
  );
}

class GalleryLightbox extends StatefulWidget {
  const GalleryLightbox({
    super.key,
    required this.items,
    required this.initialIndex,
  });

  final List<GalleryItem> items;
  final int initialIndex;

  @override
  State<GalleryLightbox> createState() => _GalleryLightboxState();
}

class _GalleryLightboxState extends State<GalleryLightbox> {
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
                    _index =
                        (_index - 1 + widget.items.length) % widget.items.length;
                  });
                },
                icon:
                    const Icon(Icons.chevron_left_rounded, color: Colors.white),
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
                icon: const Icon(
                  Icons.chevron_right_rounded,
                  color: Colors.white,
                ),
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

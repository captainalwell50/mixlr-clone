import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:video_player/video_player.dart';

import '../models.dart';
import '../theme.dart';

Future<void> showGalleryLightbox(BuildContext context, GalleryItem item) {
  return showDialog<void>(
    context: context,
    barrierColor: const Color(0xE6080C0A),
    builder: (context) => _GalleryLightbox(item: item),
  );
}

class _GalleryLightbox extends StatefulWidget {
  const _GalleryLightbox({required this.item});

  final GalleryItem item;

  @override
  State<_GalleryLightbox> createState() => _GalleryLightboxState();
}

class _GalleryLightboxState extends State<_GalleryLightbox> {
  VideoPlayerController? _player;
  String? _error;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    if (widget.item.isVideo) {
      _loadVideo();
    }
  }

  Future<void> _loadVideo() async {
    final url = widget.item.url;
    if (url.isEmpty) {
      setState(() => _error = 'This reel has no playback URL.');
      return;
    }
    final player = VideoPlayerController.networkUrl(Uri.parse(url));
    _player = player;
    try {
      await player.initialize();
      await player.setLooping(true);
      await player.play();
      if (!mounted) return;
      setState(() => _ready = true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = 'Could not play this reel. Try opening it on listen.');
    }
  }

  @override
  void dispose() {
    _player?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    final caption = item.caption?.trim().isNotEmpty == true
        ? item.caption!
        : (item.isVideo ? 'Video reel' : 'Photo');
    return Dialog(
      backgroundColor: StudioTheme.panelHi,
      insetPadding: const EdgeInsets.symmetric(horizontal: 48, vertical: 36),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 920, maxHeight: 720),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      caption,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.outfit(
                        color: StudioTheme.cream,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                    color: StudioTheme.mute,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Expanded(child: _media(item)),
              if (item.isVideo && _ready) ...[
                const SizedBox(height: 8),
                _VideoControls(player: _player!),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _media(GalleryItem item) {
    if (item.isVideo) {
      if (_error != null) {
        return Center(
          child: Text(
            _error!,
            textAlign: TextAlign.center,
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 14),
          ),
        );
      }
      if (!_ready || _player == null) {
        return const Center(
          child: CircularProgressIndicator(color: StudioTheme.accent),
        );
      }
      return ColoredBox(
        color: StudioTheme.ink,
        child: Center(
          child: AspectRatio(
            aspectRatio: _player!.value.aspectRatio == 0
                ? 16 / 9
                : _player!.value.aspectRatio,
            child: VideoPlayer(_player!),
          ),
        ),
      );
    }
    if (item.url.isEmpty) {
      return const Center(
        child: Icon(Icons.broken_image_outlined, color: StudioTheme.mute, size: 48),
      );
    }
    return InteractiveViewer(
      child: Image.network(
        item.url,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => const Center(
          child: Icon(Icons.broken_image_outlined, color: StudioTheme.mute, size: 48),
        ),
      ),
    );
  }
}

class _VideoControls extends StatelessWidget {
  const _VideoControls({required this.player});

  final VideoPlayerController player;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<VideoPlayerValue>(
      valueListenable: player,
      builder: (context, value, _) {
        return Row(
          children: [
            IconButton(
              onPressed: () {
                if (value.isPlaying) {
                  player.pause();
                } else {
                  player.play();
                }
              },
              icon: Icon(
                value.isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
              ),
              color: StudioTheme.accentBright,
            ),
            Expanded(
              child: Slider(
                value: value.position.inMilliseconds
                    .clamp(0, value.duration.inMilliseconds)
                    .toDouble(),
                max: value.duration.inMilliseconds <= 0
                    ? 1
                    : value.duration.inMilliseconds.toDouble(),
                onChanged: (v) {
                  player.seekTo(Duration(milliseconds: v.round()));
                },
                activeColor: StudioTheme.accent,
                inactiveColor: StudioTheme.line,
              ),
            ),
          ],
        );
      },
    );
  }
}

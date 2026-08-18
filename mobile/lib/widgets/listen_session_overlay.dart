import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../services/listen_controller.dart';
import '../theme.dart';
import '../screens/listen_screen.dart';

/// Persistent WHEP sink + mini-player while listen continues after pop.
class ListenSessionOverlay extends StatelessWidget {
  const ListenSessionOverlay({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final listen = context.watch<ListenController>();
    final renderer = listen.whepRenderer;

    return Stack(
      fit: StackFit.expand,
      children: [
        child,
        // Keep a tiny WebRTC view attached so remote Opus audio keeps a sink
        // even when ListenScreen is not on the navigator stack.
        if (renderer != null && !listen.uiAttached)
          Positioned(
            width: 1,
            height: 1,
            left: -10,
            top: -10,
            child: RTCVideoView(renderer),
          ),
        if (listen.showMiniPlayer)
          Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: SafeArea(
              top: false,
              child: _MiniPlayerBar(listen: listen),
            ),
          ),
      ],
    );
  }
}

class _MiniPlayerBar extends StatelessWidget {
  const _MiniPlayerBar({required this.listen});

  final ListenController listen;

  @override
  Widget build(BuildContext context) {
    final p = listen.payload;
    final title = p?.title ?? 'Listening';
    final subtitle = p?.orgName ?? 'Sound Mix Live';

    return Material(
      color: LiveMixTheme.panelHi,
      elevation: 10,
      shadowColor: Colors.black54,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          final uuid = listen.streamUuid;
          if (uuid == null) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ListenScreen(streamUuid: uuid),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: LiveMixTheme.ink,
                  borderRadius: BorderRadius.circular(10),
                  image: p?.artworkUrl != null
                      ? DecorationImage(
                          image: NetworkImage(p!.artworkUrl!),
                          fit: BoxFit.cover,
                        )
                      : null,
                ),
                child: p?.artworkUrl == null
                    ? const Icon(
                        Icons.graphic_eq_rounded,
                        color: LiveMixTheme.gold,
                        size: 22,
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.mist,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      listen.connecting
                          ? 'Connecting…'
                          : (listen.playing ? subtitle : 'Paused · $subtitle'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: LiveMixTheme.mute,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: listen.playing ? 'Pause' : 'Play',
                onPressed: () => listen.togglePlay(context: context),
                icon: Icon(
                  listen.playing
                      ? Icons.pause_rounded
                      : Icons.play_arrow_rounded,
                  color: LiveMixTheme.mist,
                ),
              ),
              IconButton(
                tooltip: 'Stop',
                onPressed: () => listen.stopSession(),
                icon: const Icon(
                  Icons.close_rounded,
                  color: LiveMixTheme.mute,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

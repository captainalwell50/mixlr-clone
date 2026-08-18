import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/models.dart';
import '../theme.dart';

/// Polished scripture board used by the Scripture screen and listen room.
class ScriptureBoardCard extends StatelessWidget {
  const ScriptureBoardCard({super.key, this.cue});

  final ScriptureCue? cue;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A3D37), Color(0xFF141C19)],
        ),
        border: Border.all(color: LiveMixTheme.accent.withOpacity(0.35)),
        boxShadow: [
          BoxShadow(
            color: LiveMixTheme.accent.withOpacity(0.12),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: _ScriptureBody(cue: cue, compact: false),
    );
  }
}

/// EasyWorship-style plate over listen artwork (matches web portal-art slide).
class ScriptureArtOverlay extends StatefulWidget {
  const ScriptureArtOverlay({super.key, this.cue});

  final ScriptureCue? cue;

  @override
  State<ScriptureArtOverlay> createState() => _ScriptureArtOverlayState();
}

class _ScriptureArtOverlayState extends State<ScriptureArtOverlay> {
  final ScrollController _scroll = ScrollController();
  bool _showScrollCue = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_syncScrollCue);
    WidgetsBinding.instance.addPostFrameCallback((_) => _syncScrollCue());
  }

  @override
  void didUpdateWidget(covariant ScriptureArtOverlay oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.cue?.text != widget.cue?.text ||
        oldWidget.cue?.ref != widget.cue?.ref) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _syncScrollCue());
    }
  }

  @override
  void dispose() {
    _scroll.removeListener(_syncScrollCue);
    _scroll.dispose();
    super.dispose();
  }

  void _syncScrollCue() {
    if (!mounted || !_scroll.hasClients) return;
    final position = _scroll.position;
    final overflow = position.maxScrollExtent > 12;
    final atBottom = position.pixels >= position.maxScrollExtent - 10;
    final show = overflow && !atBottom;
    if (show != _showScrollCue) {
      setState(() => _showScrollCue = show);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            const Color(0xF00B1220),
            LiveMixTheme.ink.withOpacity(0.92),
          ],
        ),
      ),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.menu_book_rounded,
                size: 18,
                color: LiveMixTheme.accentBright.withOpacity(0.95),
              ),
              const SizedBox(width: 8),
              Text(
                'SCRIPTURE BOARD',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.accentBright,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: LiveMixTheme.accentSoft,
                  borderRadius: BorderRadius.circular(99),
                  border: Border.all(
                    color: LiveMixTheme.accent.withOpacity(0.35),
                  ),
                ),
                child: Text(
                  widget.cue?.version ?? 'KJV',
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.accentBright,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: Stack(
              children: [
                Scrollbar(
                  controller: _scroll,
                  thumbVisibility: true,
                  thickness: 3,
                  radius: const Radius.circular(99),
                  child: SingleChildScrollView(
                    controller: _scroll,
                    physics: const BouncingScrollPhysics(),
                    padding: EdgeInsets.only(bottom: _showScrollCue ? 28 : 4),
                    child: _ScriptureBody(cue: widget.cue, compact: true),
                  ),
                ),
                if (_showScrollCue)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: IgnorePointer(
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [
                              LiveMixTheme.ink.withOpacity(0),
                              LiveMixTheme.ink.withOpacity(0.88),
                            ],
                          ),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(0, 28, 0, 2),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.keyboard_arrow_down_rounded,
                                size: 16,
                                color: LiveMixTheme.accentBright.withOpacity(0.95),
                              ),
                              const SizedBox(width: 4),
                              Text(
                                'Scroll for more',
                                style: GoogleFonts.outfit(
                                  color: LiveMixTheme.accentBright,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 0.3,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ScriptureBody extends StatelessWidget {
  const _ScriptureBody({required this.cue, required this.compact});

  final ScriptureCue? cue;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    if (cue == null) {
      return Text(
        'Waiting for the next verse…',
        style: GoogleFonts.outfit(
          color: LiveMixTheme.mute,
          fontSize: compact ? 17 : 16,
          height: 1.4,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (!compact) ...[
          Row(
            children: [
              Text(
                'SCRIPTURE BOARD',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.accentBright,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const Spacer(),
              Text(
                cue!.version.isNotEmpty ? cue!.version : 'KJV',
                style: const TextStyle(
                  color: LiveMixTheme.mute,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
        ],
        Text(
          cue!.ref,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mist,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.4,
          ),
        ),
        SizedBox(height: compact ? 10 : 12),
        Text(
          cue!.text,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mist.withOpacity(0.94),
            fontSize: compact ? 18 : 17,
            height: 1.5,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

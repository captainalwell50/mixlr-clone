import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/models.dart';
import '../theme.dart';

/// Polished scripture board used by the Scripture tab and listen room.
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
class ScriptureArtOverlay extends StatelessWidget {
  const ScriptureArtOverlay({super.key, this.cue});

  final ScriptureCue? cue;

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
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.menu_book_rounded,
                size: 16,
                color: LiveMixTheme.accentBright.withOpacity(0.95),
              ),
              const SizedBox(width: 8),
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
                  cue?.version ?? 'KJV',
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.accentBright,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Expanded(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              child: _ScriptureBody(cue: cue, compact: true),
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
          fontSize: compact ? 15 : 16,
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
            fontSize: compact ? 18 : 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.4,
          ),
        ),
        SizedBox(height: compact ? 8 : 12),
        Text(
          cue!.text,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mist.withOpacity(0.94),
            fontSize: compact ? 15 : 17,
            height: 1.45,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

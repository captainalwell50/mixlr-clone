import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme.dart';

/// Station-style creator identity under the listen stage.
///
/// Prefers [logoUrl], then [artworkUrl], then initials — always shows who
/// you're listening to without looking like a generic info card.
class CreatorStationMark extends StatelessWidget {
  const CreatorStationMark({
    super.key,
    required this.name,
    this.logoUrl,
    this.artworkUrl,
    this.themeColor,
    this.markSize = 56,
  });

  final String name;
  final String? logoUrl;
  final String? artworkUrl;
  final String? themeColor;
  final double markSize;

  String? get _imageUrl {
    final logo = logoUrl?.trim();
    if (logo != null && logo.isNotEmpty) return logo;
    final art = artworkUrl?.trim();
    if (art != null && art.isNotEmpty) return art;
    return null;
  }

  String get _initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) {
      final w = parts.first;
      return w.substring(0, w.length >= 2 ? 2 : 1).toUpperCase();
    }
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  Color get _accent {
    final raw = themeColor?.trim();
    if (raw == null || raw.isEmpty) return LiveMixTheme.accent;
    var hex = raw.startsWith('#') ? raw.substring(1) : raw;
    if (hex.length == 3) {
      hex = hex.split('').map((c) => '$c$c').join();
    }
    if (hex.length != 6) return LiveMixTheme.accent;
    final value = int.tryParse(hex, radix: 16);
    if (value == null) return LiveMixTheme.accent;
    return Color(0xFF000000 | value);
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = _imageUrl;
    final accent = _accent;
    final radius = markSize * 0.28;

    return Semantics(
      label: 'Listening to $name',
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: markSize,
            height: markSize,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(radius),
              boxShadow: [
                BoxShadow(
                  color: accent.withValues(alpha: 0.22),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(radius),
                border: Border.all(
                  color: accent.withValues(alpha: 0.45),
                  width: 1.5,
                ),
                color: LiveMixTheme.panelHi,
                gradient: imageUrl == null
                    ? LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          Color.lerp(accent, LiveMixTheme.panelHi, 0.35)!,
                          LiveMixTheme.panel,
                        ],
                      )
                    : null,
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(radius - 1),
                child: imageUrl != null
                    ? Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        width: markSize,
                        height: markSize,
                        errorBuilder: (_, __, ___) => _InitialsGlyph(
                          initials: _initials,
                          accent: accent,
                          size: markSize,
                        ),
                        loadingBuilder: (context, child, progress) {
                          if (progress == null) return child;
                          return ColoredBox(
                            color: LiveMixTheme.panelHi,
                            child: Center(
                              child: SizedBox(
                                width: markSize * 0.28,
                                height: markSize * 0.28,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: accent.withValues(alpha: 0.7),
                                ),
                              ),
                            ),
                          );
                        },
                      )
                    : _InitialsGlyph(
                        initials: _initials,
                        accent: accent,
                        size: markSize,
                      ),
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'LISTENING TO',
            style: GoogleFonts.outfit(
              color: accent,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.6,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            name,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontSize: 16,
              fontWeight: FontWeight.w700,
              letterSpacing: -0.2,
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }
}

class _InitialsGlyph extends StatelessWidget {
  const _InitialsGlyph({
    required this.initials,
    required this.accent,
    required this.size,
  });

  final String initials;
  final Color accent;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Text(
        initials,
        style: GoogleFonts.outfit(
          color: LiveMixTheme.mist,
          fontSize: size * 0.34,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.5,
        ),
      ),
    );
  }
}

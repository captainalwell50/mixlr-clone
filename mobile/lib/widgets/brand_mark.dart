import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme.dart';

/// Sound Mix Live brand mark + optional wordmark.
///
/// Header uses the vector SM mark (sharp at any DPR) plus Outfit text for
/// “Sound Mix · Live”. Avoids the soft raster lockup PNG at ~26–32 logical px.
class BrandMark extends StatelessWidget {
  const BrandMark({
    super.key,
    this.size = 56,
    this.showWordmark = true,
    this.compact = false,
  });

  final double size;
  final bool showWordmark;
  final bool compact;

  static const markSvg = 'assets/brand/soundmix-mark.svg';

  /// High-res raster for non-SVG surfaces (e.g. notification bitmaps).
  static const markPng = 'assets/brand/soundmix-mark.png';

  @override
  Widget build(BuildContext context) {
    final markSize = showWordmark ? (compact ? 28.0 : 36.0) : size;
    final mark = _BrandIcon(size: markSize, shadowed: !showWordmark && size >= 40);

    if (!showWordmark) return mark;

    final titleSize = compact ? 15.0 : 18.0;
    final wordStyle = GoogleFonts.outfit(
      color: LiveMixTheme.mist,
      fontSize: titleSize,
      fontWeight: FontWeight.w700,
      letterSpacing: -0.3,
      height: 1.1,
    );

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        mark,
        SizedBox(width: compact ? 8 : 10),
        Text.rich(
          TextSpan(
            children: [
              TextSpan(text: 'Sound Mix', style: wordStyle),
              TextSpan(
                text: ' · ',
                style: wordStyle.copyWith(color: LiveMixTheme.accent),
              ),
              TextSpan(text: 'Live', style: wordStyle),
            ],
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          semanticsLabel: 'Sound Mix Live',
        ),
      ],
    );
  }
}

class _BrandIcon extends StatelessWidget {
  const _BrandIcon({required this.size, required this.shadowed});

  final double size;
  final bool shadowed;

  @override
  Widget build(BuildContext context) {
    final icon = SvgPicture.asset(
      BrandMark.markSvg,
      width: size,
      height: size,
      fit: BoxFit.contain,
      excludeFromSemantics: true,
    );

    if (!shadowed) return icon;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF14B8A6).withOpacity(0.28),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: icon,
    );
  }
}

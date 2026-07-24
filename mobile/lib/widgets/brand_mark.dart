import 'package:flutter/material.dart';

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

  @override
  Widget build(BuildContext context) {
    if (showWordmark) {
      // On-dark horizontal lockup from public/brand/soundmix-logo-on-dark.png
      final height = compact ? 26.0 : 36.0;
      return Image.asset(
        'assets/brand/soundmix-logo.png',
        height: height,
        fit: BoxFit.contain,
        filterQuality: FilterQuality.high,
        semanticLabel: 'Sound Mix Live',
      );
    }

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
      child: Image.asset(
        'assets/brand/soundmix-icon-1024.png',
        width: size,
        height: size,
        fit: BoxFit.cover,
        filterQuality: FilterQuality.high,
      ),
    );
  }
}

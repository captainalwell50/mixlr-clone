import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../brand.dart';
import '../theme.dart';

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
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFFE2B86A), LiveMixTheme.gold, Color(0xFF9A6F2A)],
            ),
            borderRadius: BorderRadius.circular(size * 0.28),
            boxShadow: [
              BoxShadow(
                color: LiveMixTheme.gold.withOpacity(0.28),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: CustomPaint(painter: _WavePainter()),
        ),
        if (showWordmark) ...[
          SizedBox(width: compact ? 10 : 14),
          Text(
            Brand.name,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontSize: compact ? 20 : 28,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.8,
              height: 1,
            ),
          ),
        ],
      ],
    );
  }
}

class _WavePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = LiveMixTheme.ink.withOpacity(0.85)
      ..strokeWidth = size.width * 0.07
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    final cx = size.width / 2;
    final cy = size.height / 2;
    final bars = [0.28, 0.55, 0.9, 0.48, 0.32];
    final gap = size.width * 0.12;
    final startX = cx - gap * 2;

    for (var i = 0; i < bars.length; i++) {
      final h = size.height * 0.22 * bars[i];
      final x = startX + gap * i;
      canvas.drawLine(Offset(x, cy - h), Offset(x, cy + h), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

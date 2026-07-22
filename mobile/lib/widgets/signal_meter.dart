import 'package:flutter/material.dart';

import '../theme.dart';

class SignalMeter extends StatelessWidget {
  const SignalMeter({
    super.key,
    required this.level,
    required this.peak,
    this.label = 'SIGNAL',
    this.height = 140,
  });

  final double level;
  final double peak;
  final String label;
  final double height;

  @override
  Widget build(BuildContext context) {
    final color = level < 0.05
        ? LiveMixTheme.mute
        : level < 0.75
            ? LiveMixTheme.good
            : level < 0.9
                ? LiveMixTheme.warn
                : LiveMixTheme.bad;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              label,
              style: const TextStyle(
                color: LiveMixTheme.mute,
                fontSize: 11,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.2,
              ),
            ),
            const Spacer(),
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                color: color,
                shape: BoxShape.circle,
                boxShadow: level > 0.05
                    ? [BoxShadow(color: color.withOpacity(0.55), blurRadius: 8)]
                    : null,
              ),
            ),
            const SizedBox(width: 6),
            Text(
              level < 0.05 ? 'IDLE' : (level > 0.9 ? 'HOT' : 'OK'),
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: height,
          child: CustomPaint(
            painter: _MeterPainter(level: level, peak: peak),
            child: const SizedBox.expand(),
          ),
        ),
      ],
    );
  }
}

class _MeterPainter extends CustomPainter {
  _MeterPainter({required this.level, required this.peak});

  final double level;
  final double peak;

  @override
  void paint(Canvas canvas, Size size) {
    const segments = 24;
    final gap = 3.0;
    final segH = (size.height - gap * (segments - 1)) / segments;

    for (var i = 0; i < segments; i++) {
      final t = (segments - 1 - i) / (segments - 1);
      final lit = level >= t;
      final nearPeak = peak >= t && peak < t + 1 / segments;

      Color color;
      if (t > 0.9) {
        color = lit ? LiveMixTheme.bad : const Color(0x33FF5C5C);
      } else if (t > 0.75) {
        color = lit ? LiveMixTheme.warn : const Color(0x33F0B429);
      } else {
        color = lit ? LiveMixTheme.good : const Color(0x223DDC97);
      }

      final top = i * (segH + gap);
      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(0, top, size.width, segH),
        const Radius.circular(3),
      );
      canvas.drawRRect(rect, Paint()..color = color);

      if (nearPeak) {
        canvas.drawRRect(
          rect,
          Paint()
            ..color = LiveMixTheme.mist
            ..style = PaintingStyle.stroke
            ..strokeWidth = 1.2,
        );
      }
    }
  }

  @override
  bool shouldRepaint(covariant _MeterPainter oldDelegate) =>
      oldDelegate.level != level || oldDelegate.peak != peak;
}

class LiveDurationText extends StatelessWidget {
  const LiveDurationText({
    super.key,
    required this.elapsed,
    this.fontSize = 42,
  });

  final Duration elapsed;
  final double fontSize;

  String get _formatted {
    final h = elapsed.inHours;
    final m = elapsed.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');
    if (h > 0) return '$h:$m:$s';
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    return Text(
      _formatted,
      style: TextStyle(
        color: LiveMixTheme.mist,
        fontSize: fontSize,
        fontWeight: FontWeight.w800,
        fontFeatures: const [FontFeature.tabularFigures()],
        letterSpacing: 1,
      ),
    );
  }
}

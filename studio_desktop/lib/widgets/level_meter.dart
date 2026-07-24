import 'package:flutter/material.dart';

import '../theme.dart';

class LevelMeter extends StatelessWidget {
  const LevelMeter({
    super.key,
    required this.level,
    required this.peak,
    this.bars = 28,
    this.height = 56,
  });

  final double level;
  final double peak;
  final int bars;
  final double height;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final gap = 3.0;
        final barW = (constraints.maxWidth - gap * (bars - 1)) / bars;
        final lit = (level * bars).ceil().clamp(0, bars);
        final peakAt = (peak * bars).ceil().clamp(0, bars);
        final maxBarH = (height - 8).clamp(12.0, height);

        return SizedBox(
          height: height,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: List.generate(bars, (i) {
              final active = i < lit;
              final isPeak = i == peakAt - 1;
              Color color;
              if (i > bars * 0.85) {
                color = StudioTheme.live;
              } else if (i > bars * 0.65) {
                color = const Color(0xFFF0B429);
              } else {
                color = StudioTheme.accentBright;
              }
              // Only grow bar height for lit segments so silence isn't a fake graph.
              final h = active || isPeak
                  ? 4.0 + ((i + 1) / bars) * maxBarH
                  : 3.0;
              return Padding(
                padding: EdgeInsets.only(right: i == bars - 1 ? 0 : gap),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 60),
                  width: barW,
                  height: h,
                  decoration: BoxDecoration(
                    color: active || isPeak
                        ? color.withOpacity(isPeak ? 1 : 0.95)
                        : StudioTheme.panelHi,
                    borderRadius: BorderRadius.circular(3),
                    boxShadow: active
                        ? [
                            BoxShadow(
                              color: color.withOpacity(0.35),
                              blurRadius: 8,
                            ),
                          ]
                        : null,
                  ),
                ),
              );
            }),
          ),
        );
      },
    );
  }
}

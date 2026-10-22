import 'package:flutter/material.dart';

import '../theme.dart';

class LevelMeter extends StatelessWidget {
  const LevelMeter({
    super.key,
    required this.level,
    required this.peak,
    this.bars = 28,
    this.height = 56,
    this.showClip = true,
  });

  final double level;
  final double peak;
  final int bars;
  final double height;
  final bool showClip;

  @override
  Widget build(BuildContext context) {
    final isClipping = level >= 0.98 || peak >= 0.98;

    return LayoutBuilder(
      builder: (context, constraints) {
        final clipWidth = showClip ? 36.0 : 0.0;
        final availableWidth = (constraints.maxWidth - clipWidth).clamp(20.0, double.infinity);
        final gap = 3.0;
        final barW = (availableWidth - gap * (bars - 1)) / bars;
        final lit = (level * bars).ceil().clamp(0, bars);
        final peakAt = (peak * bars).ceil().clamp(0, bars);
        final maxBarH = (height - 8).clamp(12.0, height);

        return SizedBox(
          height: height,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
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
                        width: barW.clamp(1.0, 20.0),
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
              ),
              if (showClip) ...[
                const SizedBox(width: 8),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 80),
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(
                    color: isClipping ? StudioTheme.live : StudioTheme.panelHi,
                    borderRadius: BorderRadius.circular(4),
                    boxShadow: isClipping
                        ? [
                            BoxShadow(
                              color: StudioTheme.live.withOpacity(0.6),
                              blurRadius: 6,
                            ),
                          ]
                        : null,
                  ),
                  child: Text(
                    'CLIP',
                    style: TextStyle(
                      fontSize: 8.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                      color: isClipping
                          ? Colors.white
                          : StudioTheme.mute.withOpacity(0.4),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme.dart';

enum StudioWorkspace { live, advance }

/// Large, high-contrast workspace cards (not ghost outline chips).
class StudioModeSwitcher extends StatelessWidget {
  const StudioModeSwitcher({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final StudioWorkspace workspace;
  final ValueChanged<StudioWorkspace> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        StudioModeCard(
          key: const Key('studio-mode-live'),
          title: 'Live',
          subtitle: 'Mixer & broadcast',
          icon: Icons.graphic_eq_rounded,
          selected: workspace == StudioWorkspace.live,
          accent: StudioTheme.accent,
          onTap: () => onChanged(StudioWorkspace.live),
        ),
        const SizedBox(width: 10),
        StudioModeCard(
          key: const Key('studio-mode-advance'),
          title: 'AI & Advance',
          subtitle: 'Board & gallery',
          icon: Icons.auto_awesome_rounded,
          selected: workspace == StudioWorkspace.advance,
          accent: StudioTheme.live,
          glass: true,
          onTap: () => onChanged(StudioWorkspace.advance),
        ),
      ],
    );
  }
}

class StudioModeCard extends StatelessWidget {
  const StudioModeCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.selected,
    required this.onTap,
    this.accent = StudioTheme.accent,
    this.glass = false,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  final Color accent;
  final bool glass;

  @override
  Widget build(BuildContext context) {
    final Color bg;
    final Color titleColor;
    final Color subColor;
    final Color iconColor;
    final Color borderColor;
    final double elevation;

    if (glass && selected) {
      bg = StudioTheme.liveSoft;
      titleColor = StudioTheme.cream;
      subColor = StudioTheme.cream.withOpacity(0.78);
      iconColor = StudioTheme.accentBright;
      borderColor = StudioTheme.live.withOpacity(0.5);
      elevation = 0;
    } else if (selected) {
      bg = accent;
      titleColor = StudioTheme.ink;
      subColor = StudioTheme.ink.withOpacity(0.72);
      iconColor = StudioTheme.ink;
      borderColor = Colors.transparent;
      elevation = 8;
    } else {
      bg = StudioTheme.panelHi;
      titleColor = StudioTheme.cream;
      subColor = const Color(0xFFD5E0DC);
      iconColor = StudioTheme.accentBright;
      borderColor = const Color(0x66FFFFFF);
      elevation = 4;
    }

    return Material(
      color: bg,
      elevation: elevation,
      shadowColor: selected && !glass
          ? accent.withOpacity(0.45)
          : const Color(0x66000000),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: borderColor,
          width: (glass && selected) ? 1.2 : (selected ? 0 : 1.2),
        ),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Semantics(
          button: true,
          selected: selected,
          label: '$title. $subtitle',
          child: SizedBox(
            width: 214,
            height: 58,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: [
                  Icon(icon, size: 22, color: iconColor),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: GoogleFonts.outfit(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            height: 1.1,
                            color: titleColor,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.outfit(
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                            height: 1.15,
                            color: subColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}


import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../brand.dart';
import '../platform_info.dart';
import '../services/cache_store.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';

class WelcomeScreen extends StatefulWidget {
  const WelcomeScreen({super.key, required this.onContinue});

  final VoidCallback onContinue;

  @override
  State<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends State<WelcomeScreen> {
  bool _busy = false;

  Future<void> _continue() async {
    setState(() => _busy = true);
    await CacheStore().setWelcomeSeen();
    if (!mounted) return;
    widget.onContinue();
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 900 || PlatformInfo.isDesktop;

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: RadialGradient(
            center: Alignment(0.35, -0.2),
            radius: 1.15,
            colors: [
              Color(0xFF16352F),
              LiveMixTheme.ink,
              Color(0xFF080C0A),
            ],
          ),
        ),
        child: SafeArea(
          child: wide ? _DesktopWelcome(busy: _busy, onContinue: _continue) : _PhoneWelcome(busy: _busy, onContinue: _continue),
        ),
      ),
    );
  }
}

class _PhoneWelcome extends StatelessWidget {
  const _PhoneWelcome({required this.busy, required this.onContinue});

  final bool busy;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 28, 28, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const BrandMark(size: 52, compact: true),
          const Spacer(flex: 2),
          Text(
            'Broadcast.\nListen close.',
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontSize: 38,
              fontWeight: FontWeight.w800,
              height: 1.05,
              letterSpacing: -1.1,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            Brand.welcomeBody,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mute,
              fontSize: 16,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 28),
          const _Feature(
            icon: Icons.headphones_rounded,
            title: 'Listen',
            body: 'Discover live rooms and keep listening from cache when offline.',
          ),
          const SizedBox(height: 12),
          const _Feature(
            icon: Icons.mic_rounded,
            title: 'Studio',
            body: 'Go live with mic, signal meter, and on-air duration.',
          ),
          const Spacer(flex: 3),
          FilledButton(
            onPressed: busy ? null : onContinue,
            child: Text(busy ? 'Opening…' : 'Get started'),
          ),
          const SizedBox(height: 12),
          Text(
            Brand.apkHint,
            textAlign: TextAlign.center,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mute.withOpacity(0.85),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _DesktopWelcome extends StatelessWidget {
  const _DesktopWelcome({required this.busy, required this.onContinue});

  final bool busy;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 40),
      child: Row(
        children: [
          Expanded(
            flex: 5,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const BrandMark(size: 48, compact: true),
                  const Spacer(flex: 2),
                  Text(
                    'Broadcast.\nListen close.',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 52,
                      fontWeight: FontWeight.w800,
                      height: 1.02,
                      letterSpacing: -1.6,
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    Brand.welcomeBody,
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mute,
                      fontSize: 17,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 36),
                  Row(
                    children: [
                      Expanded(
                        child: _FeatureCard(
                          icon: Icons.headphones_rounded,
                          title: 'Listen',
                          body: 'Discover live rooms. Cache keeps browsing snappy offline.',
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: _FeatureCard(
                          icon: Icons.mic_rounded,
                          title: 'Studio',
                          body: 'Native mic publish with meters — go live from your desk.',
                        ),
                      ),
                    ],
                  ),
                  const Spacer(flex: 2),
                  SizedBox(
                    width: 220,
                    child: FilledButton(
                      onPressed: busy ? null : onContinue,
                      child: Text(busy ? 'Opening…' : 'Get started'),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    Brand.apkHint,
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mute.withOpacity(0.85),
                      fontSize: 12.5,
                    ),
                  ),
                  const Spacer(),
                ],
              ),
            ),
          ),
          const SizedBox(width: 40),
          const Expanded(
            flex: 5,
            child: _HeroStage(),
          ),
        ],
      ),
    );
  }
}

class _HeroStage extends StatelessWidget {
  const _HeroStage();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: AspectRatio(
        aspectRatio: 1,
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(28),
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Color(0xFF1A3D37),
                Color(0xFF0F1A17),
                Color(0xFF0A1210),
              ],
            ),
            border: Border.all(color: LiveMixTheme.accent.withOpacity(0.22)),
            boxShadow: [
              BoxShadow(
                color: LiveMixTheme.accent.withOpacity(0.12),
                blurRadius: 48,
                offset: const Offset(0, 24),
              ),
            ],
          ),
          child: Stack(
            children: [
              Positioned(
                right: -40,
                top: -40,
                child: Container(
                  width: 220,
                  height: 220,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        LiveMixTheme.accentBright.withOpacity(0.28),
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
              ),
              Positioned(
                left: -30,
                bottom: -20,
                child: Container(
                  width: 180,
                  height: 180,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        LiveMixTheme.accent.withOpacity(0.18),
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
              ),
              Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const BrandMark(size: 96, showWordmark: false),
                    const SizedBox(height: 28),
                    Text(
                      Brand.name,
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.mist,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.6,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      Brand.tagline,
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.accentBright,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 28),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: LiveMixTheme.liveSoft,
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: LiveMixTheme.live.withOpacity(0.45)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: LiveMixTheme.live,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'ON AIR',
                            style: GoogleFonts.outfit(
                              color: LiveMixTheme.mist,
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                              letterSpacing: 0.8,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FeatureCard extends StatelessWidget {
  const _FeatureCard({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel.withOpacity(0.85),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: LiveMixTheme.accent.withOpacity(0.16)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: LiveMixTheme.accentSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LiveMixTheme.accentBright, size: 22),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontWeight: FontWeight.w700,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mute,
              fontSize: 13.5,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

class _Feature extends StatelessWidget {
  const _Feature({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: LiveMixTheme.accentSoft,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: LiveMixTheme.accentBright, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mist,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                body,
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mute,
                  fontSize: 13.5,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

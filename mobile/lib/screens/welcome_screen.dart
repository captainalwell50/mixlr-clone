import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../brand.dart';
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
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF1B2030),
              LiveMixTheme.ink,
              Color(0xFF121018),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(28, 36, 28, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const BrandMark(size: 64),
                const Spacer(),
                Text(
                  'Broadcast.\nListen close.',
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mist,
                    fontSize: 40,
                    fontWeight: FontWeight.w800,
                    height: 1.05,
                    letterSpacing: -1.2,
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  Brand.welcomeBody,
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mute,
                    fontSize: 16,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 28),
                _Feature(
                  icon: Icons.headphones_rounded,
                  title: 'Listen',
                  body: 'Discover live rooms and keep listening offline from cache.',
                ),
                const SizedBox(height: 14),
                _Feature(
                  icon: Icons.mic_rounded,
                  title: 'Studio',
                  body: 'Go live from your phone mic with signal meter and duration.',
                ),
                const Spacer(),
                FilledButton(
                  onPressed: _busy ? null : _continue,
                  child: Text(_busy ? 'Opening…' : 'Get started'),
                ),
                const SizedBox(height: 12),
                Center(
                  child: Text(
                    Brand.apkHint,
                    textAlign: TextAlign.center,
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mute.withOpacity(0.8),
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
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
            color: LiveMixTheme.panel,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: LiveMixTheme.gold, size: 22),
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

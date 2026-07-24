import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../brand.dart';
import '../models/models.dart';
import '../platform_info.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/network_status.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/network_banner.dart';
import 'go_live_screen.dart';
import 'login_screen.dart';

class CreatorHomeScreen extends StatefulWidget {
  const CreatorHomeScreen({super.key});

  @override
  State<CreatorHomeScreen> createState() => _CreatorHomeScreenState();
}

class _CreatorHomeScreenState extends State<CreatorHomeScreen> {
  Future<CreatorHome>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final auth = context.watch<AuthState>();
    if (auth.isLoggedIn && _future == null) {
      _future = auth.api.creatorHome();
    }
  }

  Future<void> _refresh() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) return;
    setState(() => _future = auth.api.creatorHome());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();

    if (!auth.isLoggedIn) {
      return Scaffold(
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFF171B24), LiveMixTheme.ink],
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const BrandMark(size: 40, compact: true),
                      const Spacer(),
                      const NetworkPill(),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    PlatformInfo.isDesktop
                        ? 'Broadcast from your desk'
                        : 'Broadcast from your phone',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.7,
                      height: 1.1,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    PlatformInfo.isDesktop
                        ? 'Sign in with your ${Brand.name} creator account. Desktop Studio publishes mic audio with a native feel — playlist mixing stays on the web for now.'
                        : 'Sign in with your ${Brand.name} creator account to publish mic audio. Full playlist mixing stays on the web Studio.',
                    style: const TextStyle(color: LiveMixTheme.mute, height: 1.45, fontSize: 15),
                  ),
                  const SizedBox(height: 28),
                  FilledButton(
                    onPressed: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                      );
                      if (auth.isLoggedIn && mounted) {
                        setState(() => _future = auth.api.creatorHome());
                      }
                    },
                    child: const Text('Sign in to Studio'),
                  ),
                  const Spacer(flex: 2),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF1A1820),
              LiveMixTheme.ink,
              Color(0xFF0A0C10),
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 8, 0),
                child: Row(
                  children: [
                    const Flexible(
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: BrandMark(compact: true),
                      ),
                    ),
                    const SizedBox(width: 8),
                    const NetworkPill(),
                    IconButton(
                      onPressed: _refresh,
                      icon: const Icon(Icons.refresh_rounded),
                      tooltip: 'Refresh',
                    ),
                    IconButton(
                      onPressed: () async {
                        await auth.logout();
                        setState(() => _future = null);
                      },
                      icon: const Icon(Icons.logout_rounded),
                      tooltip: 'Sign out',
                    ),
                  ],
                ),
              ),
              Expanded(
                child: FutureBuilder<CreatorHome>(
                  future: _future,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: LiveMixTheme.gold),
                      );
                    }
                    if (snapshot.hasError) {
                      final msg = snapshot.error is ApiException
                          ? (snapshot.error as ApiException).message
                          : snapshot.error.toString();
                      final offline = !context.watch<NetworkStatus>().hasLink;
                      return Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                offline
                                    ? 'You’re offline. Studio needs a connection.'
                                    : msg,
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: LiveMixTheme.bad),
                              ),
                              const SizedBox(height: 16),
                              OutlinedButton(
                                onPressed: _refresh,
                                child: const Text('Retry'),
                              ),
                            ],
                          ),
                        ),
                      );
                    }
                    final home = snapshot.data;
                    if (home == null || !home.onboarded || home.stream == null) {
                      return const Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                          'Finish web onboarding first, then come back to go live from the app.',
                          style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
                        ),
                      );
                    }

                    final stream = home.stream!;
                    final org = home.organization;

                    return RefreshIndicator(
                      color: LiveMixTheme.gold,
                      onRefresh: _refresh,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
                        children: [
                          _StudioHeroCard(
                            orgName: org?.name ?? 'Your station',
                            email: auth.user?.email ?? '',
                            stream: stream,
                            canBroadcast: home.canBroadcast,
                            onGoLive: () => _openSession(stream, org, home.canBroadcast),
                          ),
                          if (org != null) ...[
                            const SizedBox(height: 16),
                            _ChannelShareCard(
                              org: org,
                              onShare: () => _shareChannel(org),
                              onCopy: () => _copyChannel(org),
                            ),
                          ],
                          if (home.streams.length > 1) ...[
                            const SizedBox(height: 22),
                            Text(
                              'Other streams',
                              style: GoogleFonts.outfit(
                                color: LiveMixTheme.mute,
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                                letterSpacing: 0.4,
                              ),
                            ),
                            const SizedBox(height: 10),
                            ...home.streams.where((s) => s.uuid != stream.uuid).map(
                                  (s) => Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: Material(
                                      color: LiveMixTheme.panel,
                                      borderRadius: BorderRadius.circular(14),
                                      child: ListTile(
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(14),
                                        ),
                                        title: Text(
                                          s.title,
                                          style: const TextStyle(
                                            color: LiveMixTheme.mist,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                        subtitle: Text(
                                          s.status.toUpperCase(),
                                          style: TextStyle(
                                            color: s.isLive
                                                ? LiveMixTheme.live
                                                : LiveMixTheme.mute,
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                        trailing: const Icon(
                                          Icons.chevron_right_rounded,
                                          color: LiveMixTheme.mute,
                                        ),
                                        onTap: home.canBroadcast
                                            ? () => _openSession(s, org, true)
                                            : null,
                                      ),
                                    ),
                                  ),
                                ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openSession(
    StreamSummary stream,
    OrgSummary? org,
    bool canBroadcast,
  ) async {
    if (!canBroadcast) return;
    if (!context.read<NetworkStatus>().hasLink) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Connect to the internet to go live.')),
      );
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GoLiveScreen(stream: stream, organization: org),
      ),
    );
    if (mounted) await _refresh();
  }

  Future<void> _shareChannel(OrgSummary org) async {
    await Share.share(
      'Listen live on ${org.name}: ${org.publicChannelUrl}',
      subject: org.name,
    );
  }

  Future<void> _copyChannel(OrgSummary org) async {
    await Clipboard.setData(ClipboardData(text: org.publicChannelUrl));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Channel link copied')),
    );
  }
}

class _StudioHeroCard extends StatelessWidget {
  const _StudioHeroCard({
    required this.orgName,
    required this.email,
    required this.stream,
    required this.canBroadcast,
    required this.onGoLive,
  });

  final String orgName;
  final String email;
  final StreamSummary stream;
  final bool canBroadcast;
  final VoidCallback onGoLive;

  @override
  Widget build(BuildContext context) {
    final live = stream.isLive;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: live
              ? const [Color(0xFF3A1A1C), Color(0xFF1A1E27)]
              : const [Color(0xFF2A2418), Color(0xFF1A1E27)],
        ),
        border: Border.all(
          color: live
              ? LiveMixTheme.live.withOpacity(0.45)
              : LiveMixTheme.gold.withOpacity(0.28),
        ),
        boxShadow: [
          BoxShadow(
            color: (live ? LiveMixTheme.live : LiveMixTheme.gold).withOpacity(0.12),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -20,
            top: -30,
            child: IgnorePointer(
              child: Icon(
                Icons.graphic_eq_rounded,
                size: 160,
                color: LiveMixTheme.gold.withOpacity(0.06),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: live ? LiveMixTheme.liveSoft : LiveMixTheme.goldSoft,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        live ? 'ON AIR' : 'READY',
                        style: TextStyle(
                          color: live ? LiveMixTheme.live : LiveMixTheme.gold,
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                          letterSpacing: 1.1,
                        ),
                      ),
                    ),
                    const Spacer(),
                    const BrandMark(size: 36, showWordmark: false),
                  ],
                ),
                const SizedBox(height: 18),
                Text(
                  orgName,
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mist,
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                    height: 1.1,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  stream.title,
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.gold,
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (email.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(email, style: const TextStyle(color: LiveMixTheme.mute, fontSize: 13)),
                ],
                const SizedBox(height: 14),
                Text(
                  PlatformInfo.isDesktop
                      ? Brand.studioDesktopBlurb
                      : Brand.studioPhoneBlurb,
                  style: TextStyle(
                    color: LiveMixTheme.mute.withOpacity(0.95),
                    height: 1.4,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 20),
                if (!canBroadcast)
                  Text(
                    'An active subscription is required to broadcast.',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.warn,
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: onGoLive,
                      icon: Icon(live ? Icons.radio_rounded : Icons.mic_rounded),
                      label: Text(live ? 'Open live session' : 'Go live'),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ChannelShareCard extends StatelessWidget {
  const _ChannelShareCard({
    required this.org,
    required this.onShare,
    required this.onCopy,
  });

  final OrgSummary org;
  final VoidCallback onShare;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: LiveMixTheme.panel.withOpacity(0.92),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0x22F0EBE3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'CHANNEL LINK',
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mute,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.1,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            org.publicChannelUrl,
            style: GoogleFonts.outfit(
              color: LiveMixTheme.mist,
              fontWeight: FontWeight.w600,
              fontSize: 13,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: onShare,
                  icon: const Icon(Icons.ios_share_rounded, size: 18),
                  label: const Text('Share'),
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton(
                onPressed: onCopy,
                child: const Text('Copy'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

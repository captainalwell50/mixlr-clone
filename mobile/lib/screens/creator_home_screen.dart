import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';
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
        appBar: AppBar(title: const Text('Studio')),
        body: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Go live from Android',
                style: TextStyle(
                  color: LiveMixTheme.mist,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Sign in with your Live Mix creator account to publish audio over WHIP.',
                style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  );
                  if (auth.isLoggedIn && mounted) {
                    setState(() => _future = auth.api.creatorHome());
                  }
                },
                child: const Text('Sign in'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Studio'),
        actions: [
          IconButton(
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
          IconButton(
            onPressed: () async {
              await auth.logout();
              setState(() => _future = null);
            },
            icon: const Icon(Icons.logout),
            tooltip: 'Sign out',
          ),
        ],
      ),
      body: FutureBuilder<CreatorHome>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final msg = snapshot.error is ApiException
                ? (snapshot.error as ApiException).message
                : snapshot.error.toString();
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(msg, style: const TextStyle(color: Colors.redAccent)),
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
              padding: const EdgeInsets.all(24),
              children: [
                Text(
                  org?.name ?? 'Your station',
                  style: const TextStyle(
                    color: LiveMixTheme.gold,
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  auth.user?.email ?? '',
                  style: const TextStyle(color: LiveMixTheme.mute),
                ),
                const SizedBox(height: 28),
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: LiveMixTheme.panel,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              stream.title,
                              style: const TextStyle(
                                color: LiveMixTheme.mist,
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          Text(
                            stream.status.toUpperCase(),
                            style: TextStyle(
                              color: stream.isLive
                                  ? const Color(0xFFFF6B6B)
                                  : LiveMixTheme.mute,
                              fontWeight: FontWeight.w800,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (!home.canBroadcast)
                        const Text(
                          'An active subscription is required to broadcast.',
                          style: TextStyle(color: Colors.orangeAccent),
                        )
                      else
                        FilledButton.icon(
                          onPressed: () async {
                            await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => GoLiveScreen(stream: stream),
                              ),
                            );
                            if (mounted) await _refresh();
                          },
                          icon: const Icon(Icons.mic),
                          label: Text(stream.isLive ? 'Open live session' : 'Go live'),
                        ),
                    ],
                  ),
                ),
                if (home.streams.length > 1) ...[
                  const SizedBox(height: 24),
                  const Text(
                    'Other streams',
                    style: TextStyle(
                      color: LiveMixTheme.mute,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...home.streams.where((s) => s.uuid != stream.uuid).map(
                        (s) => ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(s.title, style: const TextStyle(color: LiveMixTheme.mist)),
                          subtitle: Text(s.status, style: const TextStyle(color: LiveMixTheme.mute)),
                          trailing: const Icon(Icons.chevron_right, color: LiveMixTheme.mute),
                          onTap: home.canBroadcast
                              ? () async {
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => GoLiveScreen(stream: s),
                                    ),
                                  );
                                  if (mounted) await _refresh();
                                }
                              : null,
                        ),
                      ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

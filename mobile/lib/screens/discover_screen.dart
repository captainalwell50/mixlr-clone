import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';
import 'listen_screen.dart';

class DiscoverScreen extends StatefulWidget {
  const DiscoverScreen({super.key});

  @override
  State<DiscoverScreen> createState() => _DiscoverScreenState();
}

class _DiscoverScreenState extends State<DiscoverScreen> {
  late Future<List<DiscoverCard>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthState>().api.discover();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = context.read<AuthState>().api.discover();
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Discover'),
        actions: [
          IconButton(
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: RefreshIndicator(
        color: LiveMixTheme.gold,
        onRefresh: _refresh,
        child: FutureBuilder<List<DiscoverCard>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              final msg = snapshot.error is ApiException
                  ? (snapshot.error as ApiException).message
                  : snapshot.error.toString();
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(msg, style: const TextStyle(color: Colors.redAccent)),
                  ),
                ],
              );
            }
            final streams = snapshot.data ?? [];
            if (streams.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  Center(
                    child: Text(
                      'No live streams right now.',
                      style: TextStyle(color: LiveMixTheme.mute),
                    ),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: streams.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final card = streams[index];
                return Material(
                  color: LiveMixTheme.panel,
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ListenScreen(streamUuid: card.uuid),
                        ),
                      );
                    },
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Container(
                            width: 52,
                            height: 52,
                            decoration: BoxDecoration(
                              color: LiveMixTheme.ink,
                              borderRadius: BorderRadius.circular(10),
                              image: card.artworkUrl != null
                                  ? DecorationImage(
                                      image: NetworkImage(card.artworkUrl!),
                                      fit: BoxFit.cover,
                                    )
                                  : null,
                            ),
                            child: card.artworkUrl == null
                                ? const Icon(Icons.radio, color: LiveMixTheme.gold)
                                : null,
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  card.title,
                                  style: const TextStyle(
                                    color: LiveMixTheme.mist,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  card.organization ?? 'Live Mix',
                                  style: const TextStyle(color: LiveMixTheme.mute),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0x33E24B4B),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'LIVE',
                              style: TextStyle(
                                color: Color(0xFFFF6B6B),
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

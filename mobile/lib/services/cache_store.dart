import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';

class CacheStore {
  static const _discoverKey = 'cache_discover_v1';
  static const _welcomeKey = 'welcome_seen_v1';

  Future<bool> welcomeSeen() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_welcomeKey) ?? false;
  }

  Future<void> setWelcomeSeen() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_welcomeKey, true);
  }

  Future<void> saveDiscover(List<DiscoverCard> streams) async {
    final prefs = await SharedPreferences.getInstance();
    final payload = {
      'saved_at': DateTime.now().toIso8601String(),
      'streams': streams
          .map(
            (s) => {
              'uuid': s.uuid,
              'title': s.title,
              'status': s.status,
              'organization': s.organization,
              'theme_color': s.themeColor,
              'artwork_url': s.artworkUrl,
              'hls_url': s.hlsUrl,
            },
          )
          .toList(),
    };
    await prefs.setString(_discoverKey, jsonEncode(payload));
  }

  Future<({List<DiscoverCard> streams, DateTime? savedAt})> loadDiscover() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_discoverKey);
    if (raw == null || raw.isEmpty) {
      return (streams: <DiscoverCard>[], savedAt: null);
    }
    try {
      final map = jsonDecode(raw) as Map<String, dynamic>;
      final savedAt = DateTime.tryParse(map['saved_at'] as String? ?? '');
      final streams = (map['streams'] as List<dynamic>? ?? [])
          .map((e) => DiscoverCard.fromJson(e as Map<String, dynamic>))
          .toList();
      return (streams: streams, savedAt: savedAt);
    } catch (_) {
      return (streams: <DiscoverCard>[], savedAt: null);
    }
  }
}

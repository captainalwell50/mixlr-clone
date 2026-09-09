import 'dart:convert';
import 'dart:io';

import 'package:shared_preferences/shared_preferences.dart';

class PlaylistQueueEntry {
  const PlaylistQueueEntry({
    required this.id,
    required this.title,
    required this.url,
    this.assetId,
    this.localPath,
  });

  final String id;
  final String title;
  final String url;
  final int? assetId;
  final String? localPath;

  bool get hasRemoteUrl =>
      url.startsWith('http://') || url.startsWith('https://');

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'url': url,
        if (assetId != null) 'assetId': assetId,
        if (localPath != null && localPath!.isNotEmpty) 'localPath': localPath,
      };

  factory PlaylistQueueEntry.fromJson(Map<String, dynamic> json) {
    return PlaylistQueueEntry(
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? 'Audio',
      url: json['url'] as String? ?? '',
      assetId: (json['assetId'] as num?)?.round(),
      localPath: json['localPath'] as String?,
    );
  }

  PlaylistQueueEntry copyWith({String? localPath}) {
    return PlaylistQueueEntry(
      id: id,
      title: title,
      url: url,
      assetId: assetId,
      localPath: localPath ?? this.localPath,
    );
  }
}

/// Persists the Studio playlist queue per stream so tracks return next launch,
/// using cached files when the network is gone.
class PlaylistQueueStore {
  PlaylistQueueStore({
    Map<String, String>? memory,
    bool Function(String path)? fileExists,
  })  : _memory = memory,
        _fileExists = fileExists ?? ((path) => File(path).existsSync());

  static const prefix = 'studio_desktop.playlist_queue.';

  final Map<String, String>? _memory;
  final bool Function(String path) _fileExists;

  String _key(String streamUuid) => '$prefix$streamUuid';

  Future<List<PlaylistQueueEntry>> load(String streamUuid) async {
    if (streamUuid.isEmpty) return const [];
    final raw = await _read(_key(streamUuid));
    if (raw == null || raw.isEmpty) return const [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list
          .map((e) => PlaylistQueueEntry.fromJson(
                Map<String, dynamic>.from(e as Map),
              ))
          .where((e) => e.id.isNotEmpty)
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> save(String streamUuid, List<PlaylistQueueEntry> entries) async {
    if (streamUuid.isEmpty) return;
    await _write(
      _key(streamUuid),
      jsonEncode(entries.map((e) => e.toJson()).toList()),
    );
  }

  /// Keep entries that still have a local file or a downloadable URL.
  List<PlaylistQueueEntry> usable(List<PlaylistQueueEntry> entries) {
    return entries.where((e) {
      if (localFileReady(e)) return true;
      return e.hasRemoteUrl;
    }).toList();
  }

  bool localFileReady(PlaylistQueueEntry entry) {
    final path = entry.localPath;
    return path != null && path.isNotEmpty && _fileExists(path);
  }

  Future<String?> _read(String key) async {
    final memory = _memory;
    if (memory != null) return memory[key];
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  Future<void> _write(String key, String value) async {
    final memory = _memory;
    if (memory != null) {
      memory[key] = value;
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(key, value);
  }
}

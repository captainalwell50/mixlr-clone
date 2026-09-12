/// Pick a real audio extension for cache files and Media Foundation decode.
///
/// Signed library URLs look like `https://soundmix.live/studio/.../file?signature=...`.
/// Taking `url.split('.').last` turns that into a path with slashes, so Windows
/// cannot write or decode the cached track.
String audioFileExtension({
  String? url,
  String? filename,
  String? mimeType,
}) {
  const allowed = {
    'mp3',
    'wav',
    'm4a',
    'aac',
    'ogg',
    'flac',
    'webm',
    'mp4',
    'mpeg',
    'mpga',
  };

  String? fromName(String? name) {
    if (name == null || name.isEmpty) return null;
    final base = name.split('/').last.split('\\').last;
    final dot = base.lastIndexOf('.');
    if (dot <= 0 || dot >= base.length - 1) return null;
    final ext = base.substring(dot + 1).toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');
    if (allowed.contains(ext)) return ext;
    return null;
  }

  final named = fromName(filename);
  if (named != null) return named;

  if (url != null && url.isNotEmpty) {
    final uri = Uri.tryParse(url);
    if (uri != null && uri.hasScheme && (uri.scheme == 'http' || uri.scheme == 'https')) {
      final fromPath = fromName(uri.path);
      if (fromPath != null) return fromPath;
    } else {
      final fromLocal = fromName(url);
      if (fromLocal != null) return fromLocal;
    }
  }

  switch (mimeType) {
    case 'audio/wav':
    case 'audio/x-wav':
    case 'audio/wave':
      return 'wav';
    case 'audio/mp4':
    case 'audio/x-m4a':
      return 'm4a';
    case 'audio/aac':
      return 'aac';
    case 'audio/ogg':
    case 'application/ogg':
      return 'ogg';
    case 'audio/flac':
    case 'audio/x-flac':
      return 'flac';
    default:
      return 'mp3';
  }
}

String safeTrackCacheId(String id) {
  final cleaned = id.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
  return cleaned.isEmpty ? 'track' : cleaned;
}

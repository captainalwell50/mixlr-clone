/// HLS / WHEP helpers shared by [ListenController] and tests.
library;

/// True when the playlist is Opus-only. ExoPlayer cannot decode that.
///
/// A generic `opus` substring is not enough — CDN comments, query params, or
/// mixed AAC+Opus masters would otherwise skip a playable HLS rendition.
bool hlsPlaylistLooksLikeOpusOnly(String body) {
  final lower = body.toLowerCase();
  final hasOpusCodec = lower.contains('codecs="opus"') ||
      lower.contains("codecs='opus'") ||
      RegExp(r'codecs="[^"]*opus').hasMatch(lower);
  final hasAac = lower.contains('mp4a') ||
      lower.contains('aaclc') ||
      lower.contains('mp4a.40') ||
      RegExp(r'(^|,|\s)aac([,.\s"]|$)').hasMatch(lower);
  return hasOpusCodec && !hasAac;
}

String hlsUrlWithCacheBust(String hlsUrl) {
  final uri = Uri.tryParse(hlsUrl);
  if (uri == null || !uri.hasScheme) {
    final join = hlsUrl.contains('?') ? '&' : '?';
    return '$hlsUrl${join}_sm=${DateTime.now().millisecondsSinceEpoch}';
  }
  final params = Map<String, String>.from(uri.queryParameters);
  params['_sm'] = DateTime.now().millisecondsSinceEpoch.toString();
  return uri.replace(queryParameters: params).toString();
}

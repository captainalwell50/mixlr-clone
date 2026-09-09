import 'package:flutter/services.dart';

/// Operator-facing mixer errors. Never match on MethodChannel *codes* such as
/// `play` — `PlatformException.toString()` always contains that word.
String mixerOperatorError(Object error) {
  if (error is PlatformException) {
    return _clean(error.message) ?? _clean(error.details?.toString()) ?? _fallback(error.code);
  }
  final raw = error.toString().replaceFirst(RegExp(r'^Exception: '), '');
  return _clean(raw) ?? 'Something went wrong. Try again.';
}

String _fallback(String code) {
  switch (code) {
    case 'play':
    case 'restart':
      return 'Could not play that track. Queue it again, then Play.';
    case 'queue':
      return 'Could not add that track to the playlist. Try Queue again.';
    default:
      return 'Something went wrong. Try again.';
  }
}

String? _clean(String? raw) {
  if (raw == null) return null;
  var text = raw.trim();
  if (text.isEmpty) return null;
  text = text
      .replaceFirst(RegExp(r'^PlatformException\([^,]+, '), '')
      .replaceFirst(RegExp(r', null, null\)$'), '')
      .replaceFirst(RegExp(r', null\)$'), '')
      .replaceFirst(RegExp(r'\)$'), '')
      .replaceFirst('Exception: ', '')
      .trim();
  if (text.isEmpty) return null;

  final lower = text.toLowerCase();
  if (lower.contains('track not found') || lower.contains('not queued')) {
    return 'That track is not in the playlist. Press Queue, then Play.';
  }
  if (lower.contains('10875') ||
      lower.contains('cannot do in current context') ||
      lower.contains('avfaudio')) {
    return 'Could not play that track while live. Pause, Queue again, then Play.';
  }
  if (lower.contains('too many tracks')) {
    return 'Too many tracks playing. Pause one, then Play.';
  }
  return text;
}

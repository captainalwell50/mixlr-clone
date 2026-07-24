import 'package:flutter/foundation.dart';

/// Desktop vs phone layout helpers for Sound Mix Live.
class PlatformInfo {
  static bool get isDesktop {
    if (kIsWeb) return false;
    return defaultTargetPlatform == TargetPlatform.macOS ||
        defaultTargetPlatform == TargetPlatform.windows ||
        defaultTargetPlatform == TargetPlatform.linux;
  }

  static bool get isMobile => !isDesktop && !kIsWeb;

  /// Mic permission plugin is mobile-oriented; desktop prompts via getUserMedia.
  static bool get usesPermissionHandlerForMic => isMobile;
}

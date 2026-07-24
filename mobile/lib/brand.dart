import 'platform_info.dart';

/// Product identity for the app.
class Brand {
  static const name = 'Sound Mix Live';
  static const tagline = 'Go live. Stay close.';

  static String get welcomeBody => PlatformInfo.isDesktop
      ? 'Listen to live audio from creators you care about — or go on air from your desk in seconds.'
      : 'Listen to live audio from creators you care about — or go on air from your phone in seconds.';

  static String get apkHint => PlatformInfo.isDesktop
      ? 'Native Studio for mic publish. Full mixer stays on the web.'
      : 'Mic publish + listen on mobile. Full mixer stays on the web Studio.';

  /// Studio tab / phone publisher blurb (product-first, feature second).
  static String get studioPhoneBlurb =>
      'Sound Mix Live Studio on phone: timer + signal meter. Playlist & cues stay on the web.';

  static String get studioDesktopBlurb =>
      'Sound Mix Live Studio on desktop: timer + signal meter. Playlist & cues stay on the web.';
}

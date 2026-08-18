/// Override at build/run time:
/// `flutter run --dart-define=API_BASE=https://your.host`
class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://soundmix.live',
  );

  static String get apiV1 => '$apiBase/api/v1';

  static String get privacyUrl => '$apiBase/privacy';
  static String get termsUrl => '$apiBase/terms';
  static String get supportUrl => '$apiBase/support';
  static const String supportEmail = 'support@soundmix.live';
}

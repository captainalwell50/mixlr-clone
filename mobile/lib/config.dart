/// Override at build/run time:
/// `flutter run --dart-define=API_BASE=https://your.host`
class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://soundmix.live',
  );

  static String get apiV1 => '$apiBase/api/v1';
}

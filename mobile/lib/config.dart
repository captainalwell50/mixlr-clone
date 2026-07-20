/// Override at build/run time:
/// `flutter run --dart-define=API_BASE=https://your.host`
class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://20.120.113.129.sslip.io',
  );

  static String get apiV1 => '$apiBase/api/v1';
}

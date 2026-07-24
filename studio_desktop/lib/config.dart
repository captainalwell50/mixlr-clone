class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://soundmix.live',
  );

  static String get apiV1 => '$apiBase/api/v1';
}

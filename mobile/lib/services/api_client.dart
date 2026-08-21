import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';
import '../models/models.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;
  String? _token;

  void setToken(String? token) => _token = token;

  Map<String, String> _headers({bool auth = false, String? contentType}) {
    final headers = <String, String>{
      'Accept': 'application/json',
      if (contentType != null) 'Content-Type': contentType,
    };
    if (auth && _token != null && _token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> _json(
    http.Response response, {
    String fallback = 'Request failed',
  }) async {
    Map<String, dynamic>? body;
    if (response.body.isNotEmpty) {
      try {
        final decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic>) body = decoded;
      } catch (_) {}
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body ?? <String, dynamic>{};
    }

    final message = body?['message'] as String? ??
        (body?['errors'] is Map
            ? ((body!['errors'] as Map).values.first is List
                ? ((body['errors'] as Map).values.first as List).first.toString()
                : (body['errors'] as Map).values.first.toString())
            : null) ??
        fallback;
    throw ApiException(message, statusCode: response.statusCode);
  }

  Future<({String token, AppUser user})> login({
    required String email,
    required String password,
    String deviceName = 'android',
  }) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/auth/login'),
      headers: _headers(contentType: 'application/json'),
      body: jsonEncode({
        'email': email.trim(),
        'password': password,
        'device_name': deviceName,
      }),
    );
    final data = await _json(response, fallback: 'Login failed');
    final token = data['token'] as String;
    final user = AppUser.fromJson(data['user'] as Map<String, dynamic>);
    setToken(token);
    return (token: token, user: user);
  }

  Future<void> logout() async {
    try {
      await _client.post(
        Uri.parse('${AppConfig.apiV1}/auth/logout'),
        headers: _headers(auth: true),
      );
    } catch (_) {
      // Ignore network errors on logout.
    } finally {
      setToken(null);
    }
  }

  Future<void> deleteAccount({required String password}) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/auth/account'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'password': password}),
    );
    await _json(response, fallback: 'Could not delete account');
    setToken(null);
  }

  Future<AppUser> me() async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/me'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Session expired');
    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<AppUser> updateProfile({required String name}) async {
    final response = await _client.patch(
      Uri.parse('${AppConfig.apiV1}/me'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'name': name.trim()}),
    );
    final data = await _json(response, fallback: 'Could not update profile');
    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<AppUser> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _client.put(
      Uri.parse('${AppConfig.apiV1}/auth/password'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );
    final data = await _json(response, fallback: 'Could not change password');
    final user = data['user'];
    if (user is Map<String, dynamic>) {
      return AppUser.fromJson(user);
    }
    return me();
  }

  Future<AppUser> uploadAvatar(String filePath) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/auth/avatar'),
    );
    request.headers.addAll(_headers(auth: true));
    request.files.add(await http.MultipartFile.fromPath('avatar', filePath));
    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
    final data = await _json(response, fallback: 'Could not upload photo');
    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<CreatorHome> creatorHome() async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/creator/home'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not load studio');
    return CreatorHome.fromJson(data);
  }

  Future<PublishInfo> publish(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/publish'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Publish info unavailable');
    return PublishInfo.fromJson(data);
  }

  Future<PublishInfo> goLive(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/go-live'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not go live');
    final publish = data['publish'] as Map<String, dynamic>? ?? {};
    return PublishInfo(
      whipUrl: publish['whip_url'] as String? ?? '',
      hlsUrl: publish['hls_url'] as String?,
      whepUrl: publish['whep_url'] as String?,
      stream: data['stream'] == null
          ? null
          : StreamSummary.fromJson(data['stream'] as Map<String, dynamic>),
    );
  }

  Future<StreamSummary> pauseStream(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/pause'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not pause');
    return StreamSummary.fromJson(data['stream'] as Map<String, dynamic>);
  }

  Future<StreamSummary> endStream(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/end'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not end stream');
    return StreamSummary.fromJson(data['stream'] as Map<String, dynamic>);
  }

  Future<List<DiscoverCard>> discover() async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/discover'),
      headers: _headers(),
    );
    final data = await _json(response, fallback: 'Discover failed');
    return (data['streams'] as List<dynamic>? ?? [])
        .map((e) => DiscoverCard.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<ListenPayload> listen(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/listen/$streamUuid'),
      headers: _headers(auth: _token != null),
    );
    final data = await _json(response, fallback: 'Stream not found');
    return ListenPayload.fromJson(data);
  }

  Future<String> listenStatus(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/listen/$streamUuid/status'),
      headers: _headers(auth: _token != null),
    );
    final data = await _json(response, fallback: 'Status failed');
    return data['status'] as String? ?? 'offline';
  }

  Future<Map<String, dynamic>> presence(
    String streamUuid, {
    String? sessionKey,
  }) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/listen/$streamUuid/presence'),
      headers: _headers(
        auth: _token != null,
        contentType: 'application/json',
      ),
      body: jsonEncode({
        if (sessionKey != null) 'session_key': sessionKey,
      }),
    );
    return _json(response, fallback: 'Presence failed');
  }

  Future<int> like(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/listen/$streamUuid/like'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Like failed');
    return data['likes'] as int? ?? 0;
  }

  /// Public listen-portal scripture/song poll (same as web `/listen/{uuid}/scripture`).
  Future<({bool enabled, ScriptureCue? cue, SongCue? song})> scripture(
    String streamUuid,
  ) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiBase}/listen/$streamUuid/scripture'),
      headers: _headers(auth: _token != null),
    );
    final data = await _json(response, fallback: 'Could not load scripture');
    final enabled = data['enabled'] as bool? ?? false;
    ScriptureCue? cue;
    final raw = data['scripture'];
    if (raw is Map<String, dynamic>) {
      final parsed = ScriptureCue.fromJson(raw);
      if (parsed.ref.isNotEmpty && parsed.text.isNotEmpty) {
        cue = parsed;
      }
    }
    SongCue? song;
    final songRaw = data['song'];
    if (songRaw is Map<String, dynamic>) {
      final parsed = SongCue.fromJson(songRaw);
      if (parsed.title.isNotEmpty && parsed.text.isNotEmpty) {
        song = parsed;
      }
    }
    return (enabled: enabled, cue: cue, song: song);
  }

  /// Public listen-portal gallery (same as web `/listen/{uuid}/gallery`).
  Future<List<GalleryItem>> gallery(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiBase}/listen/$streamUuid/gallery'),
      headers: _headers(auth: _token != null),
    );
    final data = await _json(response, fallback: 'Could not load gallery');
    final images = data['images'] as List<dynamic>? ?? [];
    return images
        .map((e) => GalleryItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config.dart';
import '../models.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

typedef UnauthorizedHandler = FutureOr<void> Function();

class ApiClient {
  ApiClient({http.Client? client, Duration? timeout})
      : _client = client ?? http.Client(),
        _timeout = timeout ?? const Duration(seconds: 30);

  final http.Client _client;
  final Duration _timeout;
  String? _token;
  UnauthorizedHandler? onUnauthorized;

  void setToken(String? token) => _token = token;

  Map<String, String> _headers({bool auth = false, String? contentType}) {
    return {
      'Accept': 'application/json',
      if (contentType != null) 'Content-Type': contentType,
      if (auth && _token != null && _token!.isNotEmpty)
        'Authorization': 'Bearer $_token',
    };
  }

  Future<http.Response> _send(Future<http.Response> future) async {
    try {
      return await future.timeout(_timeout);
    } on TimeoutException {
      throw ApiException('Request timed out. Check your connection and try again.');
    } on SocketException {
      throw ApiException('Network unavailable. Check your connection and try again.');
    } on HttpException catch (e) {
      throw ApiException('Network error: ${e.message}');
    } on HandshakeException {
      throw ApiException('Secure connection failed. Check your network or VPN.');
    }
  }

  String _extractMessage(Map<String, dynamic>? body, String fallback) {
    final message = body?['message'];
    if (message is String &&
        message.isNotEmpty &&
        message != 'The given data was invalid.') {
      return message;
    }
    final errors = body?['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) return first.first.toString();
      if (first != null) return first.toString();
    }
    return fallback;
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

    if (response.statusCode == 401) {
      final handler = onUnauthorized;
      if (handler != null) {
        await handler();
      }
      throw ApiException(
        _extractMessage(body, 'Session expired. Sign in again.'),
        statusCode: 401,
      );
    }

    throw ApiException(
      _extractMessage(body, fallback),
      statusCode: response.statusCode,
    );
  }

  Future<Map<String, dynamic>> _parseMultipart(
    http.StreamedResponse streamed, {
    required String fallback,
  }) async {
    final response = await http.Response.fromStream(streamed).timeout(_timeout);
    return _json(response, fallback: fallback);
  }

  Future<({String token, AppUser user})> login({
    required String email,
    required String password,
  }) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/auth/login'),
      headers: _headers(contentType: 'application/json'),
      body: jsonEncode({
        'email': email.trim(),
        'password': password,
        'device_name': 'soundmix-studio-desktop',
      }),
    ));
    final data = await _json(response, fallback: 'Login failed');
    final token = data['token'] as String?;
    final userJson = data['user'] as Map<String, dynamic>?;
    if (token == null || token.isEmpty || userJson == null) {
      throw ApiException('Login response was incomplete.');
    }
    final user = AppUser.fromJson(userJson);
    setToken(token);
    return (token: token, user: user);
  }

  Future<void> logout() async {
    try {
      if (_token != null) {
        await _send(_client.post(
          Uri.parse('${AppConfig.apiV1}/auth/logout'),
          headers: _headers(auth: true),
        ));
      }
    } catch (_) {
    } finally {
      setToken(null);
    }
  }

  Future<AppUser> me() async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/me'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Session expired');
    final userJson = data['user'] as Map<String, dynamic>?;
    if (userJson == null) throw ApiException('Session expired');
    return AppUser.fromJson(userJson);
  }

  Future<CreatorHome> creatorHome() async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/creator/home'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Could not load studio');
    return CreatorHome.fromJson(data);
  }

  Future<PublishInfo> publish(String streamUuid) async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/publish'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Publish info unavailable');
    return PublishInfo.fromJson(data);
  }

  /// Marks the service event live (creates/resumes) and returns WHIP endpoints + event.
  Future<PublishInfo> goLive(String streamUuid, {int? eventId, String? title}) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/go-live'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({
        if (eventId != null) 'event_id': eventId,
        if (title != null && title.isNotEmpty) 'title': title,
      }),
    ));
    final data = await _json(response, fallback: 'Could not go live');
    final publish = data['publish'] as Map<String, dynamic>? ?? {};
    final eventJson = data['event'] as Map<String, dynamic>?;
    return PublishInfo(
      whipUrl: publish['whip_url'] as String? ?? '',
      hlsUrl: publish['hls_url'] as String?,
      whepUrl: publish['whep_url'] as String?,
      stream: data['stream'] == null
          ? null
          : StreamSummary.fromJson(data['stream'] as Map<String, dynamic>),
      event: eventJson == null ? null : EventSummary.fromJson(eventJson),
    );
  }

  Future<({StreamSummary stream, EventSummary? event})> pauseStream(
    String streamUuid,
  ) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/pause'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Could not pause');
    final eventJson = data['event'] as Map<String, dynamic>?;
    return (
      stream: StreamSummary.fromJson(data['stream'] as Map<String, dynamic>),
      event: eventJson == null ? null : EventSummary.fromJson(eventJson),
    );
  }

  Future<({StreamSummary stream, EventSummary? event})> endStream(
    String streamUuid,
  ) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/end'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Could not end stream');
    final eventJson = data['event'] as Map<String, dynamic>?;
    return (
      stream: StreamSummary.fromJson(data['stream'] as Map<String, dynamic>),
      event: eventJson == null ? null : EventSummary.fromJson(eventJson),
    );
  }

  Future<({String embedUrl, String whipUrl})> desktopMixer(String streamUuid) async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/desktop-mixer'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Mixer engine unavailable');
    return (
      embedUrl: data['embed_url'] as String? ?? '',
      whipUrl: data['whip_url'] as String? ?? '',
    );
  }

  Future<List<LibraryAsset>> library(String streamUuid, {String? q}) async {
    final uri = Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/library').replace(
      queryParameters: q == null || q.isEmpty ? null : {'q': q},
    );
    final response = await _send(_client.get(uri, headers: _headers(auth: true)));
    final data = await _json(response, fallback: 'Could not load library');
    final assets = data['assets'] as List<dynamic>? ?? [];
    return assets
        .map((e) => LibraryAsset.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<LibraryAsset> uploadLibraryAsset({
    required String streamUuid,
    required String path,
    String? title,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/library'),
    );
    request.headers.addAll(_headers(auth: true));
    if (title != null && title.isNotEmpty) {
      request.fields['title'] = title;
    }
    request.files.add(await http.MultipartFile.fromPath('audio', path));
    final streamed = await _client.send(request).timeout(_timeout);
    final data = await _parseMultipart(streamed, fallback: 'Upload failed');
    final asset = data['asset'] as Map<String, dynamic>?;
    if (asset == null) throw ApiException('Upload failed — empty response.');
    return LibraryAsset.fromJson(asset);
  }

  Future<void> deleteLibraryAsset(String streamUuid, int assetId) async {
    final response = await _send(_client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/library/$assetId'),
      headers: _headers(auth: true),
    ));
    await _json(response, fallback: 'Could not delete asset');
  }

  Future<List<GalleryItem>> gallery(String streamUuid, {int? eventId}) async {
    final uri = Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery').replace(
      queryParameters: eventId == null ? null : {'event_id': '$eventId'},
    );
    final response = await _send(_client.get(uri, headers: _headers(auth: true)));
    final data = await _json(response, fallback: 'Could not load gallery');
    final images = data['images'] as List<dynamic>? ?? [];
    return images
        .map((e) => GalleryItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<GalleryItem> uploadGalleryImage({
    required String streamUuid,
    required String path,
    String? caption,
    int? eventId,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery'),
    );
    request.headers.addAll(_headers(auth: true));
    if (caption != null && caption.isNotEmpty) {
      request.fields['caption'] = caption;
    }
    if (eventId != null) {
      request.fields['event_id'] = '$eventId';
    }
    request.files.add(await http.MultipartFile.fromPath('image', path));
    final streamed = await _client.send(request).timeout(_timeout);
    final data = await _parseMultipart(streamed, fallback: 'Photo upload failed');
    final raw = data['image'] ?? data['item'];
    if (raw is! Map<String, dynamic>) {
      throw ApiException('Photo upload failed — unexpected response.');
    }
    return GalleryItem.fromJson(raw);
  }

  Future<GalleryItem> uploadGalleryReel({
    required String streamUuid,
    required String path,
    String? caption,
    int? eventId,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery'),
    );
    request.headers.addAll(_headers(auth: true));
    if (caption != null && caption.isNotEmpty) {
      request.fields['caption'] = caption;
    }
    if (eventId != null) {
      request.fields['event_id'] = '$eventId';
    }
    request.files.add(await http.MultipartFile.fromPath('video', path));
    final streamed = await _client.send(request).timeout(_timeout);
    final data = await _parseMultipart(streamed, fallback: 'Reel upload failed');
    final raw = data['image'] ?? data['item'];
    if (raw is! Map<String, dynamic>) {
      throw ApiException('Reel upload failed — unexpected response.');
    }
    return GalleryItem.fromJson(raw);
  }

  Future<void> deleteGalleryItem(String streamUuid, int imageId) async {
    final response = await _send(_client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery/$imageId'),
      headers: _headers(auth: true),
    ));
    await _json(response, fallback: 'Could not delete gallery item');
  }

  Future<String?> uploadListenBackground({
    required String streamUuid,
    required String path,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery/background'),
    );
    request.headers.addAll(_headers(auth: true));
    request.files.add(await http.MultipartFile.fromPath('image', path));
    final streamed = await _client.send(request).timeout(_timeout);
    final data =
        await _parseMultipart(streamed, fallback: 'Background upload failed');
    return data['background_url'] as String?;
  }

  Future<({ScriptureCue? cue, String? liveBoard})> scriptureShow(
    String streamUuid,
  ) async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Could not load scripture');
    final cue = data['scripture'];
    return (
      cue: cue is Map<String, dynamic> ? ScriptureCue.fromJson(cue) : null,
      liveBoard: data['live_board'] as String?,
    );
  }

  Future<List<ScriptureSuggestion>> scriptureSuggest(
    String streamUuid,
    String q,
  ) async {
    final uri = Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture/suggest')
        .replace(queryParameters: {'q': q});
    final response = await _send(_client.get(uri, headers: _headers(auth: true)));
    final data = await _json(response, fallback: 'Could not search scripture');
    final items = data['suggestions'] as List<dynamic>? ?? [];
    return items
        .map((e) => ScriptureSuggestion.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<ScriptureCue> scriptureStore(String streamUuid, String ref) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'ref': ref}),
    ));
    final data = await _json(response, fallback: 'Could not show scripture');
    final cue = data['scripture'];
    if (cue is! Map<String, dynamic>) {
      throw ApiException('Could not show scripture');
    }
    return ScriptureCue.fromJson(cue);
  }

  Future<void> scriptureClear(String streamUuid) async {
    final response = await _send(_client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true),
    ));
    await _json(response, fallback: 'Could not clear scripture');
  }

  Future<({List<DisplaySongItem> songs, SongCue? cue, String? liveBoard})>
      songsIndex(String streamUuid) async {
    final response = await _send(_client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs'),
      headers: _headers(auth: true),
    ));
    final data = await _json(response, fallback: 'Could not load songs');
    final songs = (data['songs'] as List<dynamic>? ?? [])
        .map((e) => DisplaySongItem.fromJson(e as Map<String, dynamic>))
        .toList();
    final cueRaw = data['cue'] ?? data['song'];
    final cue = cueRaw is Map<String, dynamic> ? SongCue.fromJson(cueRaw) : null;
    return (
      songs: songs,
      cue: cue,
      liveBoard: data['live_board'] as String?,
    );
  }

  Future<DisplaySongItem> songStore(
    String streamUuid, {
    required String title,
    required String body,
  }) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'title': title, 'body': body}),
    ));
    final data = await _json(response, fallback: 'Could not save song');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not save song');
    }
    return DisplaySongItem.fromJson(song);
  }

  Future<DisplaySongItem> songUpdate(
    String streamUuid,
    int songId, {
    required String title,
    required String body,
  }) async {
    final response = await _send(_client.put(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'title': title, 'body': body}),
    ));
    final data = await _json(response, fallback: 'Could not update song');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not update song');
    }
    return DisplaySongItem.fromJson(song);
  }

  Future<void> songDelete(String streamUuid, int songId) async {
    final response = await _send(_client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId'),
      headers: _headers(auth: true),
    ));
    await _json(response, fallback: 'Could not delete song');
  }

  Future<SongCue> songCue(String streamUuid, int songId, {int slideIndex = 0}) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId/cue'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'slide_index': slideIndex}),
    ));
    final data = await _json(response, fallback: 'Could not cue song');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not cue song');
    }
    return SongCue.fromJson(song);
  }

  Future<SongCue> songNext(String streamUuid) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue/next'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: '{}',
    ));
    final data = await _json(response, fallback: 'Could not advance slide');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not advance slide');
    }
    return SongCue.fromJson(song);
  }

  Future<SongCue> songPrevious(String streamUuid) async {
    final response = await _send(_client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue/previous'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: '{}',
    ));
    final data = await _json(response, fallback: 'Could not go to previous slide');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not go to previous slide');
    }
    return SongCue.fromJson(song);
  }

  Future<void> songClear(String streamUuid) async {
    final response = await _send(_client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue'),
      headers: _headers(auth: true),
    ));
    await _json(response, fallback: 'Could not clear song');
  }
}

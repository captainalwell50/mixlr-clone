import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';
import '../models.dart';

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
    return {
      'Accept': 'application/json',
      if (contentType != null) 'Content-Type': contentType,
      if (auth && _token != null && _token!.isNotEmpty) 'Authorization': 'Bearer $_token',
    };
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
  }) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/auth/login'),
      headers: _headers(contentType: 'application/json'),
      body: jsonEncode({
        'email': email.trim(),
        'password': password,
        'device_name': 'soundmix-studio-desktop',
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
    } finally {
      setToken(null);
    }
  }

  Future<AppUser> me() async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/me'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Session expired');
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

  Future<({String embedUrl, String whipUrl})> desktopMixer(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/desktop-mixer'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Mixer engine unavailable');
    return (
      embedUrl: data['embed_url'] as String,
      whipUrl: data['whip_url'] as String? ?? '',
    );
  }

  Future<List<LibraryAsset>> library(String streamUuid, {String? q}) async {
    final uri = Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/library').replace(
      queryParameters: q == null || q.isEmpty ? null : {'q': q},
    );
    final response = await _client.get(uri, headers: _headers(auth: true));
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
    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
    final data = await _json(response, fallback: 'Upload failed');
    return LibraryAsset.fromJson(data['asset'] as Map<String, dynamic>);
  }

  Future<void> deleteLibraryAsset(String streamUuid, int assetId) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/library/$assetId'),
      headers: _headers(auth: true),
    );
    await _json(response, fallback: 'Could not delete asset');
  }

  Future<List<GalleryItem>> gallery(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery'),
      headers: _headers(auth: true),
    );
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
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery'),
    );
    request.headers.addAll(_headers(auth: true));
    if (caption != null && caption.isNotEmpty) {
      request.fields['caption'] = caption;
    }
    request.files.add(await http.MultipartFile.fromPath('image', path));
    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
    final data = await _json(response, fallback: 'Photo upload failed');
    return GalleryItem.fromJson(data['image'] as Map<String, dynamic>);
  }

  Future<GalleryItem> uploadGalleryReel({
    required String streamUuid,
    required String path,
    String? caption,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery'),
    );
    request.headers.addAll(_headers(auth: true));
    if (caption != null && caption.isNotEmpty) {
      request.fields['caption'] = caption;
    }
    request.files.add(await http.MultipartFile.fromPath('video', path));
    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
    final data = await _json(response, fallback: 'Reel upload failed');
    return GalleryItem.fromJson(data['image'] as Map<String, dynamic>);
  }

  Future<void> deleteGalleryItem(String streamUuid, int imageId) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/gallery/$imageId'),
      headers: _headers(auth: true),
    );
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
    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
    final data = await _json(response, fallback: 'Background upload failed');
    return data['background_url'] as String?;
  }

  Future<ScriptureCue?> scriptureShow(String streamUuid) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not load scripture');
    final cue = data['scripture'];
    if (cue is! Map<String, dynamic>) return null;
    return ScriptureCue.fromJson(cue);
  }

  Future<List<ScriptureSuggestion>> scriptureSuggest(String streamUuid, String q) async {
    final uri = Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture/suggest')
        .replace(queryParameters: {'q': q});
    final response = await _client.get(uri, headers: _headers(auth: true));
    final data = await _json(response, fallback: 'Could not search scripture');
    final items = data['suggestions'] as List<dynamic>? ?? [];
    return items
        .map((e) => ScriptureSuggestion.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<ScriptureCue> scriptureStore(String streamUuid, String ref) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'ref': ref}),
    );
    final data = await _json(response, fallback: 'Could not show scripture');
    final cue = data['scripture'];
    if (cue is! Map<String, dynamic>) {
      throw ApiException('Could not show scripture');
    }
    return ScriptureCue.fromJson(cue);
  }

  Future<void> scriptureClear(String streamUuid) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/scripture'),
      headers: _headers(auth: true),
    );
    await _json(response, fallback: 'Could not clear scripture');
  }

  Future<({List<DisplaySongItem> songs, SongCue? cue})> songsIndex(
    String streamUuid,
  ) async {
    final response = await _client.get(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs'),
      headers: _headers(auth: true),
    );
    final data = await _json(response, fallback: 'Could not load songs');
    final songs = (data['songs'] as List<dynamic>? ?? [])
        .map((e) => DisplaySongItem.fromJson(e as Map<String, dynamic>))
        .toList();
    final cueRaw = data['cue'];
    final cue = cueRaw is Map<String, dynamic> ? SongCue.fromJson(cueRaw) : null;
    return (songs: songs, cue: cue);
  }

  Future<DisplaySongItem> songStore(
    String streamUuid, {
    required String title,
    required String body,
  }) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'title': title, 'body': body}),
    );
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
    final response = await _client.put(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'title': title, 'body': body}),
    );
    final data = await _json(response, fallback: 'Could not update song');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not update song');
    }
    return DisplaySongItem.fromJson(song);
  }

  Future<void> songDelete(String streamUuid, int songId) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId'),
      headers: _headers(auth: true),
    );
    await _json(response, fallback: 'Could not delete song');
  }

  Future<SongCue> songCue(String streamUuid, int songId, {int slideIndex = 0}) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/$songId/cue'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: jsonEncode({'slide_index': slideIndex}),
    );
    final data = await _json(response, fallback: 'Could not cue song');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not cue song');
    }
    return SongCue.fromJson(song);
  }

  Future<SongCue> songNext(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue/next'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: '{}',
    );
    final data = await _json(response, fallback: 'Could not advance slide');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not advance slide');
    }
    return SongCue.fromJson(song);
  }

  Future<SongCue> songPrevious(String streamUuid) async {
    final response = await _client.post(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue/previous'),
      headers: _headers(auth: true, contentType: 'application/json'),
      body: '{}',
    );
    final data = await _json(response, fallback: 'Could not go to previous slide');
    final song = data['song'];
    if (song is! Map<String, dynamic>) {
      throw ApiException('Could not go to previous slide');
    }
    return SongCue.fromJson(song);
  }

  Future<void> songClear(String streamUuid) async {
    final response = await _client.delete(
      Uri.parse('${AppConfig.apiV1}/streams/$streamUuid/songs/cue'),
      headers: _headers(auth: true),
    );
    await _json(response, fallback: 'Could not clear song');
  }
}

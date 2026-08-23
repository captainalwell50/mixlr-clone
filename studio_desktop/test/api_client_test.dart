import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:soundmix_studio/services/api_client.dart';

void main() {
  test('goLive parses event + nested publish whip_url', () async {
    final client = ApiClient(
      client: MockClient((request) async {
        expect(request.url.path, contains('/go-live'));
        return http.Response(
          jsonEncode({
            'stream': {
              'uuid': 'abc',
              'title': 'Live',
              'status': 'live',
              'listen_url': 'https://soundmix.live/listen/abc',
            },
            'event': {
              'id': 42,
              'uuid': 'evt',
              'title': 'Sunday',
              'status': 'live',
              'url': 'https://soundmix.live/e/evt',
            },
            'publish': {
              'whip_url': 'https://example.test/whip/abc',
              'hls_url': 'https://example.test/hls/abc',
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    client.setToken('test');
    final info = await client.goLive('abc');
    expect(info.whipUrl, 'https://example.test/whip/abc');
    expect(info.event?.id, 42);
    expect(info.stream?.isLive, isTrue);
  });

  test('gallery upload failure surfaces validation message', () async {
    final tmp = File('${Directory.systemTemp.path}/studio_gallery_test.jpg');
    await tmp.writeAsBytes(const [0xFF, 0xD8, 0xFF, 0xD9]);
    addTearDown(() {
      if (tmp.existsSync()) tmp.deleteSync();
    });

    final client = ApiClient(
      client: MockClient((request) async {
        return http.Response(
          jsonEncode({
            'message': 'The given data was invalid.',
            'errors': {
              'event_id': [
                'Create or go live to an event before posting to the service gallery.',
              ],
            },
          }),
          422,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    client.setToken('test');
    await expectLater(
      client.uploadGalleryImage(streamUuid: 'abc', path: tmp.path),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          contains('Create or go live'),
        ),
      ),
    );
  });

  test('scriptureShow parses last-cue-wins live_board', () async {
    final client = ApiClient(
      client: MockClient((request) async {
        expect(request.url.path, contains('/scripture'));
        return http.Response(
          jsonEncode({
            'enabled': true,
            'live_board': 'song',
            'scripture': null,
            'song': {
              'id': 9,
              'title': 'Holy Holy',
              'text': 'Holy, holy, holy',
              'slide_index': 0,
              'slide_count': 2,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    client.setToken('test');
    final result = await client.scriptureShow('abc');
    expect(result.liveBoard, 'song');
    expect(result.cue, isNull);
  });

  test('songsIndex keeps cue only when live_board is song', () async {
    final client = ApiClient(
      client: MockClient((request) async {
        expect(request.url.path, contains('/songs'));
        return http.Response(
          jsonEncode({
            'songs': [
              {
                'id': 3,
                'title': 'Holy Holy',
                'slides': ['Holy, holy, holy'],
                'slide_count': 1,
              },
            ],
            'live_board': 'scripture',
            'cue': null,
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    client.setToken('test');
    final result = await client.songsIndex('abc');
    expect(result.liveBoard, 'scripture');
    expect(result.songs, hasLength(1));
    expect(result.cue, isNull);
  });

  test('deleteGalleryItem hits streams gallery id', () async {
    final client = ApiClient(
      client: MockClient((request) async {
        expect(request.method, 'DELETE');
        expect(request.url.path, endsWith('/streams/abc/gallery/17'));
        return http.Response(
          jsonEncode({'ok': true}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    client.setToken('test');
    await client.deleteGalleryItem('abc', 17);
  });

  test('network timeout becomes ApiException', () async {
    final client = ApiClient(
      timeout: const Duration(milliseconds: 40),
      client: MockClient((request) async {
        await Future<void>.delayed(const Duration(milliseconds: 200));
        return http.Response('{}', 200);
      }),
    );
    await expectLater(
      client.me(),
      throwsA(isA<ApiException>().having(
        (e) => e.message,
        'message',
        contains('timed out'),
      )),
    );
  });
}

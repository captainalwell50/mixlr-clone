import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:soundmix_studio/services/api_client.dart';
import 'package:soundmix_studio/widgets/scripture_panel.dart';
import 'package:soundmix_studio/widgets/song_panel.dart';

Widget _advanceShell({required Widget child, Size size = const Size(1400, 800)}) {
  return MaterialApp(
    home: MediaQuery(
      data: MediaQueryData(size: size),
      child: Scaffold(
        body: Column(
          children: [
            const SizedBox(height: 64),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                child: child,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Mirrors the c5d8142 wide Advance split: ListView(Column) + gallery Expanded.
Widget _portedWideAdvance({required Widget board}) {
  return LayoutBuilder(
    builder: (context, constraints) {
      return Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            flex: 5,
            child: ListView(
              children: [board],
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            flex: 6,
            child: Container(
              padding: const EdgeInsets.all(18),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('SERVICE GALLERY'),
                  SizedBox(height: 10),
                  Expanded(child: Center(child: Text('No photos or reels yet.'))),
                ],
              ),
            ),
          ),
        ],
      );
    },
  );
}

const _realSongs = [
  {
    'id': 4,
    'title': 'God Has Glorified Me - Glory...',
    'slides': [
      'Verse:\nI am the head and not the tail',
      'Chorus:\nGod has glorified me',
      'INTERLUDE\nLord has G, L, O, R, Y',
      'CALL AND RESPONSE:\nCall: I’ve got a glory',
      'G, L, O, R, Y, …G – 3x – GLORY',
    ],
    'slide_count': 5,
  },
  {
    'id': 3,
    'title': 'MY HOPE IS BUILT ON NOTHING ELSE',
    'slides': [
      '1 My hope is built on nothing less',
      'Refrain:\nOn Christ, the solid Rock, I stand',
      '2 When darkness veils his lovely face',
      '3 His oath, his covenant, his blood',
      '4 When he shall come with trumpet sound',
    ],
    'slide_count': 5,
  },
];

ApiClient _studioApi({List<Map<String, Object?>> songs = const []}) {
  return ApiClient(
    client: MockClient((request) async {
      if (request.url.path.contains('/scripture')) {
        return http.Response(
          jsonEncode({
            'enabled': true,
            'live_board': null,
            'scripture': null,
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }
      if (request.url.path.contains('/songs')) {
        return http.Response(
          jsonEncode({
            'songs': songs,
            'live_board': null,
            'cue': null,
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }
      return http.Response('{}', 200);
    }),
  );
}

void main() {
  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  testWidgets('ported wide Advance layout with dummy board', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    await tester.pumpWidget(
      _advanceShell(
        child: _portedWideAdvance(
          board: const Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('SCRIPTURE'),
              SizedBox(height: 16),
              Text('SONG'),
            ],
          ),
        ),
      ),
    );
    expect(tester.takeException(), isNull);
    expect(find.text('No photos or reels yet.'), findsOneWidget);
  });

  testWidgets('Advance opens with empty scripture/song/gallery', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    final api = _studioApi();
    await tester.pumpWidget(
      _advanceShell(
        child: _portedWideAdvance(
          board: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScripturePanel(api: api, streamUuid: 'abc'),
              const SizedBox(height: 16),
              SongPanel(api: api, streamUuid: 'abc'),
            ],
          ),
        ),
      ),
    );
    await tester.pump();
    final err = tester.takeException();
    expect(err, isNull, reason: '$err');
    expect(find.text('SCRIPTURE'), findsOneWidget);
    expect(find.text('SONG'), findsOneWidget);
    expect(find.text('No songs yet'), findsOneWidget);
    await tester.pumpAndSettle(const Duration(milliseconds: 50));
  });

  testWidgets('Advance with real songs and slide lists', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    final api = _studioApi(songs: _realSongs);
    await tester.pumpWidget(
      _advanceShell(
        child: _portedWideAdvance(
          board: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScripturePanel(api: api, streamUuid: 'abc'),
              const SizedBox(height: 16),
              SongPanel(api: api, streamUuid: 'abc'),
            ],
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    final err = tester.takeException();
    expect(err, isNull, reason: '$err');
    expect(find.text('God Has Glorified Me - Glory...'), findsOneWidget);
    expect(find.text('MY HOPE IS BUILT ON NOTHING ELSE'), findsOneWidget);
    expect(find.text('Go live'), findsWidgets);
  });

  testWidgets('New song form posts title and slides', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    var stored = false;
    final songs = <Map<String, Object?>>[];
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.contains('/scripture')) {
          return http.Response(
            jsonEncode({'enabled': true, 'live_board': null, 'scripture': null}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        if (request.method == 'POST' && request.url.path.endsWith('/songs')) {
          stored = true;
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          songs.add({
            'id': 21,
            'title': body['title'],
            'slides': body['slides'],
            'slide_count': (body['slides'] as List).length,
          });
          return http.Response(
            jsonEncode({'ok': true, 'song': songs.last}),
            201,
            headers: {'content-type': 'application/json'},
          );
        }
        if (request.url.path.contains('/songs')) {
          return http.Response(
            jsonEncode({'songs': songs, 'live_board': null, 'cue': null}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response('{}', 200);
      }),
    );
    await tester.pumpWidget(
      _advanceShell(
        child: _portedWideAdvance(
          board: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SongPanel(api: api, streamUuid: 'abc'),
            ],
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.tap(find.text('New song'));
    await tester.pump();
    expect(find.text('Save'), findsOneWidget);
    await tester.enterText(find.byType(TextField).at(0), 'Amazing Grace');
    await tester.enterText(find.byType(TextField).at(1), 'Amazing grace\n\nHow sweet');
    await tester.tap(find.text('Save'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(stored, isTrue);
    expect(find.text('Amazing Grace'), findsWidgets);
    expect(find.text('Song saved'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Listen for scripture does not throw without mixer', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    final api = _studioApi();
    await tester.pumpWidget(
      _advanceShell(
        child: ScripturePanel(api: api, streamUuid: 'abc'),
      ),
    );
    await tester.pump();
    await tester.tap(find.text('Listen for scripture'));
    await tester.pump();
    // speech_to_text initialize is async; allow a short settle without requiring
    // a specific status (plugin often unavailable in widget tests).
    await tester.pump(const Duration(milliseconds: 500));
    expect(tester.takeException(), isNull);
  });

  testWidgets('Advance narrow ListView + Expanded gallery', (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 700));
    final api = _studioApi();
    await tester.pumpWidget(
      _advanceShell(
        size: const Size(800, 700),
        child: LayoutBuilder(
          builder: (context, constraints) {
            return ListView(
              children: [
                ScripturePanel(api: api, streamUuid: 'abc'),
                const SizedBox(height: 16),
                SongPanel(api: api, streamUuid: 'abc'),
                const SizedBox(height: 16),
                SizedBox(
                  height: constraints.maxHeight,
                  child: const Column(
                    children: [
                      Text('SERVICE GALLERY'),
                      Expanded(child: Center(child: Text('empty gallery'))),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
    await tester.pump();
    final err = tester.takeException();
    expect(err, isNull, reason: '$err');
  });

  testWidgets('Scripture Prev/Next cue adjacent verses via store', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1400, 800));
    final posted = <String>[];
    var liveRef = 'John 3:16';
    var liveText = 'For God so loved the world';
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'GET' &&
            request.url.path.endsWith('/scripture') &&
            !request.url.path.contains('suggest')) {
          return http.Response(
            jsonEncode({
              'enabled': true,
              'live_board': 'scripture',
              'scripture': {'ref': liveRef, 'text': liveText},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        if (request.method == 'POST' && request.url.path.endsWith('/scripture')) {
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          final ref = body['ref'] as String;
          posted.add(ref);
          liveRef = ref;
          liveText = 'text for $ref';
          return http.Response(
            jsonEncode({
              'ok': true,
              'live_board': 'scripture',
              'scripture': {'ref': liveRef, 'text': liveText},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response('{}', 200);
      }),
    );
    await tester.pumpWidget(
      _advanceShell(child: ScripturePanel(api: api, streamUuid: 'abc')),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(find.text('LIVE: John 3:16'), findsOneWidget);
    expect(find.text('Prev'), findsOneWidget);
    expect(find.text('Next'), findsOneWidget);

    await tester.tap(find.text('Next'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(posted, ['John 3:17']);
    expect(find.text('LIVE: John 3:17'), findsOneWidget);

    await tester.tap(find.text('Prev'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(posted, ['John 3:17', 'John 3:16']);
    expect(find.text('LIVE: John 3:16'), findsOneWidget);
  });
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:live_mix/models/models.dart';
import 'package:live_mix/screens/listen_screen.dart';
import 'package:live_mix/services/api_client.dart';
import 'package:live_mix/theme.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  group('ListenPayload Private Event Access State', () {
    test('parses password_required access state with minimal safe metadata', () {
      final json = {
        'access': 'password_required',
        'capability': null,
        'stream': {
          'uuid': 'stream-uuid-123',
          'title': 'Midweek Service',
          'description': 'Private broadcast',
          'status': 'live',
          'chat_enabled': false,
          'hls_url': null,
          'whep_url': null,
          'playback_mode': 'whep',
          'prefer_hls': false,
          'private_listen': true,
        },
        'organization': {
          'name': 'Grace Church',
          'slug': 'grace-church',
        },
      };

      final payload = ListenPayload.fromJson(json);

      expect(payload.uuid, 'stream-uuid-123');
      expect(payload.title, 'Midweek Service');
      expect(payload.access, 'password_required');
      expect(payload.isPasswordRequired, isTrue);
      expect(payload.isAccessGranted, isFalse);
      expect(payload.capability, isNull);
      expect(payload.whepUrl, isNull);
      expect(payload.hlsUrl, isNull);
      expect(payload.privateListen, isTrue);
      expect(payload.orgName, 'Grace Church');
    });

    test('parses granted access state with capability token', () {
      final json = {
        'access': 'granted',
        'capability': 'token_xyz_123',
        'stream': {
          'uuid': 'stream-uuid-123',
          'title': 'Midweek Service',
          'status': 'live',
          'hls_url': null,
          'whep_url': 'https://stream.soundmix.live/whep?media=token_xyz_123',
          'playback_mode': 'whep',
          'prefer_hls': false,
          'private_listen': true,
        },
        'organization': {
          'name': 'Grace Church',
          'slug': 'grace-church',
        },
      };

      final payload = ListenPayload.fromJson(json);

      expect(payload.access, 'granted');
      expect(payload.isPasswordRequired, isFalse);
      expect(payload.isAccessGranted, isTrue);
      expect(payload.capability, 'token_xyz_123');
      expect(payload.whepUrl, contains('media=token_xyz_123'));
      expect(payload.hlsUrl, isNull);
      expect(payload.privateListen, isTrue);
    });

    test('parses public event access state', () {
      final json = {
        'access': 'public',
        'capability': null,
        'stream': {
          'uuid': 'stream-pub-123',
          'title': 'Public Praise',
          'status': 'live',
          'hls_url': 'https://stream.soundmix.live/hls/index.m3u8',
          'whep_url': 'https://stream.soundmix.live/whep',
          'playback_mode': 'hls',
          'prefer_hls': true,
          'private_listen': false,
        },
      };

      final payload = ListenPayload.fromJson(json);

      expect(payload.access, 'public');
      expect(payload.isPasswordRequired, isFalse);
      expect(payload.isAccessGranted, isTrue);
      expect(payload.capability, isNull);
      expect(payload.hlsUrl, isNotNull);
      expect(payload.whepUrl, isNotNull);
      expect(payload.privateListen, isFalse);
    });
  });

  group('ApiClient Listen & Unlock', () {
    test('listen passes media query param when capability is present', () async {
      String? requestedUrl;
      final mock = MockClient((request) async {
        requestedUrl = request.url.toString();
        return http.Response(
          jsonEncode({
            'access': 'granted',
            'capability': 'cap123',
            'stream': {
              'uuid': 'stream-1',
              'title': 'Live',
              'status': 'live',
              'private_listen': true,
              'whep_url': 'https://media.test/whep?media=cap123',
            },
          }),
          200,
        );
      });

      final client = ApiClient(client: mock);
      final payload = await client.listen('stream-1', mediaCapability: 'cap123');

      expect(requestedUrl, contains('media=cap123'));
      expect(payload.access, 'granted');
      expect(payload.capability, 'cap123');
    });

    test('listen does not include media query param when capability is absent', () async {
      String? requestedUrl;
      final mock = MockClient((request) async {
        requestedUrl = request.url.toString();
        return http.Response(
          jsonEncode({
            'access': 'password_required',
            'stream': {
              'uuid': 'stream-1',
              'title': 'Private',
              'status': 'live',
              'private_listen': true,
            },
          }),
          200,
        );
      });

      final client = ApiClient(client: mock);
      final payload = await client.listen('stream-1');

      expect(requestedUrl, isNot(contains('media=')));
      expect(payload.isPasswordRequired, isTrue);
    });

    test('unlockStream posts password and parses returned capability', () async {
      String? postedBody;
      final mock = MockClient((request) async {
        postedBody = request.body;
        return http.Response(
          jsonEncode({
            'access': 'granted',
            'capability': 'minted_token_777',
            'stream': {
              'uuid': 'stream-1',
              'title': 'Private Service',
              'status': 'live',
              'private_listen': true,
              'whep_url': 'https://media.test/whep?media=minted_token_777',
            },
          }),
          200,
        );
      });

      final client = ApiClient(client: mock);
      final payload = await client.unlockStream('stream-1', 'mypassword');

      expect(postedBody, jsonEncode({'password': 'mypassword'}));
      expect(payload.isAccessGranted, isTrue);
      expect(payload.capability, 'minted_token_777');
    });

    test('unlockStream throws ApiException with message on 422 wrong password', () async {
      final mock = MockClient((request) async {
        return http.Response(
          jsonEncode({'message': "That access password wasn't accepted."}),
          422,
        );
      });

      final client = ApiClient(client: mock);

      expect(
        () => client.unlockStream('stream-1', 'badpass'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.message, 'message', "That access password wasn't accepted.")
              .having((e) => e.statusCode, 'statusCode', 422),
        ),
      );
    });

    test('unlockStream throws ApiException with message on 429 rate limit', () async {
      final mock = MockClient((request) async {
        return http.Response(
          jsonEncode({'message': 'Too many attempts. Try again shortly.'}),
          429,
        );
      });

      final client = ApiClient(client: mock);

      expect(
        () => client.unlockStream('stream-1', 'badpass'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.message, 'message', 'Too many attempts. Try again shortly.')
              .having((e) => e.statusCode, 'statusCode', 429),
        ),
      );
    });

    test('unlock does not persist passwords or capabilities to persistent storage', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();

      final mock = MockClient((request) async {
        return http.Response(
          jsonEncode({
            'access': 'granted',
            'capability': 'minted_token_777',
            'stream': {
              'uuid': 'stream-1',
              'title': 'Private Service',
              'status': 'live',
              'private_listen': true,
              'whep_url': 'https://media.test/whep?media=minted_token_777',
            },
          }),
          200,
        );
      });

      final client = ApiClient(client: mock);
      await client.unlockStream('stream-1', 'supersecretpassword');

      expect(prefs.getKeys(), isEmpty);
      expect(prefs.getString('password'), isNull);
      expect(prefs.getString('capability'), isNull);
    });
  });

  group('Private Unlock UI View', () {
    testWidgets('renders obscured password field, title, and submits password', (tester) async {
      final payload = ListenPayload(
        uuid: 'test-uuid',
        title: 'Secret Service',
        status: 'live',
        access: 'password_required',
        privateListen: true,
        orgName: 'Grace Chapel',
      );

      String? submittedPassword;
      await tester.pumpWidget(
        MaterialApp(
          theme: LiveMixTheme.dark(),
          home: Scaffold(
            body: PrivateUnlockView(
              payload: payload,
              loading: false,
              errorMessage: null,
              onUnlock: (password) async {
                submittedPassword = password;
                return true;
              },
            ),
          ),
        ),
      );

      expect(find.text('Secret Service'), findsOneWidget);
      expect(find.text('Grace Chapel'), findsOneWidget);
      expect(find.text('This broadcast requires an access password.'), findsOneWidget);
      expect(find.text('Unlock & Listen'), findsOneWidget);

      final textFieldFinder = find.byType(TextField);
      expect(textFieldFinder, findsOneWidget);
      final textField = tester.widget<TextField>(textFieldFinder);
      expect(textField.obscureText, isTrue);

      // Enter password and tap Unlock & Listen
      await tester.enterText(textFieldFinder, 'mySecretKey');
      await tester.tap(find.text('Unlock & Listen'));
      await tester.pump();

      expect(submittedPassword, 'mySecretKey');
    });

    testWidgets('displays error message when provided', (tester) async {
      final payload = ListenPayload(
        uuid: 'test-uuid',
        title: 'Secret Service',
        status: 'live',
        access: 'password_required',
        privateListen: true,
        orgName: 'Grace Chapel',
      );

      await tester.pumpWidget(
        MaterialApp(
          theme: LiveMixTheme.dark(),
          home: Scaffold(
            body: PrivateUnlockView(
              payload: payload,
              loading: false,
              errorMessage: "That access password wasn't accepted.",
              onUnlock: (_) async => false,
            ),
          ),
        ),
      );

      expect(find.text("That access password wasn't accepted."), findsOneWidget);
    });

    testWidgets('displays rate limit error message when provided', (tester) async {
      final payload = ListenPayload(
        uuid: 'test-uuid',
        title: 'Secret Service',
        status: 'live',
        access: 'password_required',
        privateListen: true,
      );

      await tester.pumpWidget(
        MaterialApp(
          theme: LiveMixTheme.dark(),
          home: Scaffold(
            body: PrivateUnlockView(
              payload: payload,
              loading: false,
              errorMessage: 'Too many attempts. Try again shortly.',
              onUnlock: (_) async => false,
            ),
          ),
        ),
      );

      expect(find.text('Too many attempts. Try again shortly.'), findsOneWidget);
    });
  });
}

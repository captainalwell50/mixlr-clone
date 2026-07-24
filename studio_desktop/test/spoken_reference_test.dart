import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/scripture/spoken_reference.dart';

void main() {
  group('parseSpokenReference', () {
    test('digit refs with colon (STT form)', () {
      expect(parseSpokenReference('John 3:16'), 'John 3:16');
      expect(parseSpokenReference('Romans 8:28'), 'Romans 8:28');
      expect(parseSpokenReference('Please open your Bibles to John 3:16'), 'John 3:16');
    });

    test('digit refs with spaces', () {
      expect(parseSpokenReference('John 3 16'), 'John 3:16');
      expect(parseSpokenReference('Psalm 23 1'), 'Psalms 23:1');
    });

    test('spoken word numbers', () {
      expect(parseSpokenReference('John three sixteen'), 'John 3:16');
      expect(parseSpokenReference('john chapter three verse sixteen'), 'John 3:16');
      expect(parseSpokenReference('Romans eight twenty eight'), 'Romans 8:28');
    });

    test('numbered epistles', () {
      expect(parseSpokenReference('First John 1:9'), '1 John 1:9');
      expect(parseSpokenReference('1 John 1:9'), '1 John 1:9');
      expect(parseSpokenReference('second timothy 3 16'), '2 Timothy 3:16');
    });

    test('ranges', () {
      expect(parseSpokenReference('John 3:16-17'), 'John 3:16-17');
      expect(parseSpokenReference('John 3 16 17'), 'John 3:16-17');
    });

    test('rejects non-refs', () {
      expect(parseSpokenReference('hello church'), isNull);
      expect(parseSpokenReference('John'), isNull);
    });
  });
}

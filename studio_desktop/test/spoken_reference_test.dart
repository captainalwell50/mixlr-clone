import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/scripture/spoken_reference.dart';

void main() {
  group('parseSpokenReference', () {
    test('digit refs with colon (STT form)', () {
      expect(parseSpokenReference('John 3:16'), 'John 3:16');
      expect(parseSpokenReference('Romans 8:28'), 'Romans 8:28');
      expect(parseSpokenReference('Please open your Bibles to John 3:16'), 'John 3:16');
    });

    test('digit refs with spaces (no verse word)', () {
      expect(parseSpokenReference('John 3 16'), 'John 3:16');
      expect(parseSpokenReference('Psalm 23 1'), 'Psalms 23:1');
    });

    test('spoken word numbers without chapter/verse words', () {
      expect(parseSpokenReference('John three sixteen'), 'John 3:16');
      expect(parseSpokenReference('John thirteen sixteen'), 'John 13:16');
      expect(parseSpokenReference('john chapter three verse sixteen'), 'John 3:16');
      expect(parseSpokenReference('Romans eight twenty eight'), 'Romans 8:28');
      expect(parseSpokenReference('Matthew twenty three thirty one'), 'Matthew 23:31');
    });

    test('tens+ones alone prefer chapter:verse', () {
      expect(parseSpokenReference('Psalms thirty one'), 'Psalms 30:1');
      expect(parseSpokenReference('John twenty one fifteen'), 'John 21:15');
    });

    test('start from / begin at after chapter', () {
      expect(parseSpokenReference('John 13 start from verse 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 start from 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 begin at verse 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 begin at 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 beginning at 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 starting at 16'), 'John 13:16');
      expect(parseSpokenReference('John 13 starting from 16'), 'John 13:16');
      expect(
        parseSpokenReference('John thirteen start from sixteen'),
        'John 13:16',
      );
      expect(
        parseSpokenReference('open John chapter thirteen beginning at verse sixteen'),
        'John 13:16',
      );
    });

    test('numbered epistles', () {
      expect(parseSpokenReference('First John 1:9'), '1 John 1:9');
      expect(parseSpokenReference('1 John 1:9'), '1 John 1:9');
      expect(parseSpokenReference('second timothy 3 16'), '2 Timothy 3:16');
    });

    test('ranges', () {
      expect(parseSpokenReference('John 3:16-17'), 'John 3:16-17');
      expect(parseSpokenReference('John 3 16 17'), 'John 3:16-17');
      expect(parseSpokenReference('John three sixteen seventeen'), 'John 3:16-17');
    });

    test('rejects non-refs', () {
      expect(parseSpokenReference('hello church'), isNull);
      expect(parseSpokenReference('John'), isNull);
    });

    test('does not invent 1:7-1 from restated chapter after Ecclesiastes 1:7', () {
      const transcript =
          'give me a crest one or seven Ecclesiastes one by seven '
          'ecclesiastics chapter 1 verse seven OK now';
      expect(parseSpokenReference(transcript), 'Ecclesiastes 1:7');
    });

    test('one by seven is chapter:verse not a range', () {
      expect(parseSpokenReference('Ecclesiastes one by seven'), 'Ecclesiastes 1:7');
    });

    test('prefers the last complete reference in a long transcript', () {
      const transcript =
          'give me John chapter 2 verse nine right and again '
          'Ecclesiastes one by seven ecclesiastics chapter 1 verse seven '
          'OK now let us go in the book of John chapter 2 verse nine';
      expect(parseSpokenReference(transcript), 'John 2:9');
    });

    test('restated chapter digit does not become a descending range', () {
      expect(
        parseSpokenReference('John three sixteen chapter three verse sixteen'),
        'John 3:16',
      );
    });
  });
}

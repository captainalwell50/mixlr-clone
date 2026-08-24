import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/scripture/verse_navigation.dart';

void main() {
  group('parseScriptureRef', () {
    test('parses single verse and aliases', () {
      final ref = parseScriptureRef('Jn 3:16');
      expect(ref, isNotNull);
      expect(ref!.book, 'John');
      expect(ref.chapter, 3);
      expect(ref.verseStart, 16);
      expect(ref.verseEnd, 16);
      expect(ref.display, 'John 3:16');
    });

    test('parses ranges', () {
      final ref = parseScriptureRef('1 Cor 13:4-5');
      expect(ref, isNotNull);
      expect(ref!.book, '1 Corinthians');
      expect(ref.verseStart, 4);
      expect(ref.verseEnd, 5);
    });

    test('rejects out-of-range verses', () {
      expect(parseScriptureRef('John 3:99'), isNull);
      expect(parseScriptureRef('John 999:1'), isNull);
      expect(parseScriptureRef('NotABook 1:1'), isNull);
    });
  });

  group('nextVerseRef / previousVerseRef', () {
    test('steps within a chapter', () {
      expect(nextVerseRef('John 3:16'), 'John 3:17');
      expect(previousVerseRef('John 3:16'), 'John 3:15');
    });

    test('crosses chapter boundaries within a book', () {
      expect(nextVerseRef('John 3:36'), 'John 4:1');
      expect(previousVerseRef('John 4:1'), 'John 3:36');
      expect(nextVerseRef('Psalms 119:176'), 'Psalms 120:1');
      expect(previousVerseRef('Genesis 2:1'), 'Genesis 1:31');
    });

    test('stops at book ends', () {
      expect(previousVerseRef('Genesis 1:1'), isNull);
      expect(nextVerseRef('John 21:25'), isNull);
      expect(nextVerseRef('Revelation 22:21'), isNull);
      expect(previousVerseRef('2 John 1:1'), isNull);
      expect(nextVerseRef('3 John 1:15'), isNull);
    });

    test('ranges advance from end / retreat from start', () {
      expect(nextVerseRef('John 3:16-17'), 'John 3:18');
      expect(previousVerseRef('John 3:16-17'), 'John 3:15');
    });
  });
}

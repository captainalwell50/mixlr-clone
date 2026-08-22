import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/scripture/book_completion.dart';

void main() {
  test('ecc uniquely expands to Ecclesiastes', () {
    final hint = inferBookCompletion('ecc');
    expect(hint, isNotNull);
    expect(hint!.book, 'Ecclesiastes');
    expect(hint.unique, isTrue);
    expect(hint.expanded, 'Ecclesiastes ');
    expect(hint.ghostSuffix, 'lesiastes');
  });

  test('jn uniquely expands to John', () {
    final hint = inferBookCompletion('jn');
    expect(hint, isNotNull);
    expect(hint!.book, 'John');
    expect(hint.unique, isTrue);
    expect(hint.expanded, 'John ');
  });

  test('j is ambiguous and ghosts the first canon match', () {
    final hint = inferBookCompletion('j');
    expect(hint, isNotNull);
    expect(hint!.unique, isFalse);
    expect(hint.book, 'Joshua');
    expect(hint.ghostSuffix, 'oshua');
    expect(hint.expanded, 'Joshua ');
  });

  test('chapter rest is preserved when expanding the book', () {
    final hint = inferBookCompletion('ecc 3:16');
    expect(hint, isNotNull);
    expect(hint!.expanded, 'Ecclesiastes 3:16');
    expect(hint.unique, isTrue);
  });
}

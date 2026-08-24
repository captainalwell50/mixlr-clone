import 'book_completion.dart';
import 'kjv_chapter_verse_counts.dart';

/// Parsed scripture reference used for Prev/Next verse stepping.
class ParsedScriptureRef {
  const ParsedScriptureRef({
    required this.book,
    required this.chapter,
    required this.verseStart,
    required this.verseEnd,
  });

  final String book;
  final int chapter;
  final int verseStart;
  final int verseEnd;

  String get display {
    if (verseStart == verseEnd) {
      return '$book $chapter:$verseStart';
    }
    return '$book $chapter:$verseStart-$verseEnd';
  }
}

/// Parse a typed/live cue like `John 3:16` or `1 Cor 13:4-5`.
ParsedScriptureRef? parseScriptureRef(String raw) {
  final trimmed = raw.trim().replaceAll(RegExp(r'\s+'), ' ');
  if (trimmed.isEmpty) return null;

  final m = RegExp(
    r'^(.+?)\s+(\d+)\s*:\s*(\d+)(?:\s*[-–—]\s*(\d+))?$',
    unicode: true,
  ).firstMatch(trimmed);
  if (m == null) return null;

  final books = matchBooks(m.group(1)!);
  if (books.isEmpty) return null;
  final book = books.first;
  final chapters = kjvChapterVerseCounts[book];
  if (chapters == null) return null;

  final chapter = int.parse(m.group(2)!);
  final start = int.parse(m.group(3)!);
  final endRaw = m.group(4);
  final end = endRaw == null || endRaw.isEmpty ? start : int.parse(endRaw);

  if (chapter < 1 || chapter > chapters.length) return null;
  final maxVerse = chapters[chapter - 1];
  if (start < 1 || start > maxVerse) return null;
  if (end < start || end > maxVerse) return null;

  return ParsedScriptureRef(
    book: book,
    chapter: chapter,
    verseStart: start,
    verseEnd: end,
  );
}

/// Next single verse after [ref] (from the range end). Crosses chapters within
/// the same book; returns null at the last verse of the book.
String? nextVerseRef(String raw) {
  final ref = parseScriptureRef(raw);
  if (ref == null) return null;
  final chapters = kjvChapterVerseCounts[ref.book]!;
  final maxVerse = chapters[ref.chapter - 1];
  if (ref.verseEnd < maxVerse) {
    return '${ref.book} ${ref.chapter}:${ref.verseEnd + 1}';
  }
  if (ref.chapter < chapters.length) {
    return '${ref.book} ${ref.chapter + 1}:1';
  }
  return null;
}

/// Previous single verse before [ref] (from the range start). Crosses chapters
/// within the same book; returns null at the first verse of the book.
String? previousVerseRef(String raw) {
  final ref = parseScriptureRef(raw);
  if (ref == null) return null;
  final chapters = kjvChapterVerseCounts[ref.book]!;
  if (ref.verseStart > 1) {
    return '${ref.book} ${ref.chapter}:${ref.verseStart - 1}';
  }
  if (ref.chapter > 1) {
    final prevChapter = ref.chapter - 1;
    final last = chapters[prevChapter - 1];
    return '${ref.book} $prevChapter:$last';
  }
  return null;
}

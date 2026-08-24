/// Parse a spoken (or typed-from-STT) Bible reference into canonical form.
///
/// Handles common church cues: "John 3:16", "John three sixteen",
/// "chapter 3 verse 16", "John 13 start from 16", "first John 1:9".
///
/// When a transcript contains multiple book cues, prefers the **last**
/// complete chapter:verse (most recent spoken reference). Ranges are only
/// formed when the end verse is strictly after the start (avoids STT noise
/// like restated "chapter 1" turning `1:7` into `1:7-1`).
String? parseSpokenReference(String transcript) {
  // Word numbers through ninety-nine cover Bible chapter/verse speech
  // ("sixteen", "thirty one", "twenty-eight"). Hyphens are stripped below.
  const words = <String, int>{
    'zero': 0,
    'oh': 0,
    'o': 0,
    'one': 1,
    'two': 2,
    'three': 3,
    'four': 4,
    'five': 5,
    'six': 6,
    'seven': 7,
    'eight': 8,
    'nine': 9,
    'ten': 10,
    'eleven': 11,
    'twelve': 12,
    'thirteen': 13,
    'fourteen': 14,
    'fifteen': 15,
    'sixteen': 16,
    'seventeen': 17,
    'eighteen': 18,
    'nineteen': 19,
    'twenty': 20,
    'thirty': 30,
    'forty': 40,
    'fifty': 50,
    'sixty': 60,
    'seventy': 70,
    'eighty': 80,
    'ninety': 90,
  };

  const books = <String, String>{
    'first corinthians': '1 Corinthians',
    'second corinthians': '2 Corinthians',
    'first thessalonians': '1 Thessalonians',
    'second thessalonians': '2 Thessalonians',
    'first timothy': '1 Timothy',
    'second timothy': '2 Timothy',
    'first samuel': '1 Samuel',
    'second samuel': '2 Samuel',
    'first kings': '1 Kings',
    'second kings': '2 Kings',
    'first chronicles': '1 Chronicles',
    'second chronicles': '2 Chronicles',
    'first peter': '1 Peter',
    'second peter': '2 Peter',
    'first john': '1 John',
    'second john': '2 John',
    'third john': '3 John',
    'song of solomon': 'Song of Solomon',
    'song of songs': 'Song of Solomon',
    'genesis': 'Genesis',
    'exodus': 'Exodus',
    'leviticus': 'Leviticus',
    'numbers': 'Numbers',
    'deuteronomy': 'Deuteronomy',
    'joshua': 'Joshua',
    'judges': 'Judges',
    'ruth': 'Ruth',
    'ezra': 'Ezra',
    'nehemiah': 'Nehemiah',
    'esther': 'Esther',
    'job': 'Job',
    'psalms': 'Psalms',
    'psalm': 'Psalms',
    'proverbs': 'Proverbs',
    'ecclesiastes': 'Ecclesiastes',
    'ecclesiastics': 'Ecclesiastes',
    'isaiah': 'Isaiah',
    'jeremiah': 'Jeremiah',
    'lamentations': 'Lamentations',
    'ezekiel': 'Ezekiel',
    'daniel': 'Daniel',
    'hosea': 'Hosea',
    'joel': 'Joel',
    'amos': 'Amos',
    'obadiah': 'Obadiah',
    'jonah': 'Jonah',
    'micah': 'Micah',
    'nahum': 'Nahum',
    'habakkuk': 'Habakkuk',
    'zephaniah': 'Zephaniah',
    'haggai': 'Haggai',
    'zechariah': 'Zechariah',
    'malachi': 'Malachi',
    'matthew': 'Matthew',
    'mark': 'Mark',
    'luke': 'Luke',
    'john': 'John',
    'acts': 'Acts',
    'romans': 'Romans',
    'galatians': 'Galatians',
    'ephesians': 'Ephesians',
    'philippians': 'Philippians',
    'colossians': 'Colossians',
    'titus': 'Titus',
    'philemon': 'Philemon',
    'hebrews': 'Hebrews',
    'james': 'James',
    'jude': 'Jude',
    'revelation': 'Revelation',
    'revelations': 'Revelation',
  };

  var text = ' ${transcript.toLowerCase()} ';
  // STT often returns "John 3:16" — split chapter:verse before tokenization.
  text = text.replaceAll(RegExp(r'[.:;,\-–—/\\!?…]'), ' ');
  text = text
      .replaceAll('turn with me to', ' ')
      .replaceAll('please open', ' ')
      .replaceAll(RegExp(r'open your bibles? to'), ' ')
      .replaceAll('in the book of', ' ')
      .replaceAll(RegExp(r'\bchapters?\b'), ' ')
      .replaceAll(RegExp(r'\bverses?\b'), ' ')
      // "John 13 start from 16" / "begin at" / "beginning at" / "starting from"
      .replaceAll(
        RegExp(r'\b(start|starting|begin|beginning)\s+(from|at)\b'),
        ' ',
      )
      .replaceAll(RegExp(r'\b(start|starting|begin|beginning)\b'), ' ')
      .replaceAll(RegExp(r'\bfrom\b'), ' ')
      .replaceAll(RegExp(r'\bat\b'), ' ')
      .replaceAll(RegExp(r'\band\b'), ' ')
      .replaceAll(RegExp(r'\bthrough\b'), ' ')
      .replaceAll(RegExp(r'\bto\b'), ' ')
      // "Ecclesiastes one by seven" — STT for chapter/verse separator.
      .replaceAll(RegExp(r'\bby\b'), ' ');

  // Digits / roman cues → spoken ordinal book names ("1 john" → "first john").
  text = text
      .replaceAllMapped(
        RegExp(
          r'\b(1|2|3|i|ii|iii|one|two|three|first|second|third)\s+'
          r'(john|peter|corinthians|thessalonians|timothy|samuel|kings|chronicles)\b',
        ),
        (m) {
          const map = {
            '1': 'first',
            'i': 'first',
            'one': 'first',
            'first': 'first',
            '2': 'second',
            'ii': 'second',
            'two': 'second',
            'second': 'second',
            '3': 'third',
            'iii': 'third',
            'three': 'third',
            'third': 'third',
          };
          final ord = map[m.group(1)!] ?? m.group(1)!;
          return ' $ord ${m.group(2)} ';
        },
      )
      .replaceAll(RegExp(r'\s+'), ' ');

  final sorted = books.keys.toList()..sort((a, b) => b.length.compareTo(a.length));

  // Collect every book hit; later complete refs win over earlier ones.
  final hits = <({String book, int idx, int len})>[];
  for (final spoken in sorted) {
    final needle = ' $spoken ';
    var from = 0;
    while (true) {
      final idx = text.indexOf(needle, from);
      if (idx == -1) break;
      hits.add((book: books[spoken]!, idx: idx, len: needle.length));
      from = idx + 1;
    }
  }
  if (hits.isEmpty) return null;

  // Drop nested hits ("john" inside "first john").
  hits.sort((a, b) {
    final byIdx = a.idx.compareTo(b.idx);
    if (byIdx != 0) return byIdx;
    return b.len.compareTo(a.len);
  });
  final kept = <({String book, int idx, int len})>[];
  for (final h in hits) {
    final nested = kept.any(
      (k) => h.idx >= k.idx && h.idx < k.idx + k.len,
    );
    if (!nested) kept.add(h);
  }

  String? best;
  var bestIdx = -1;
  for (final h in kept) {
    final ref = _chapterVerseFromRest(
      text.substring(h.idx + h.len),
      h.book,
      words,
    );
    if (ref == null) continue;
    if (h.idx >= bestIdx) {
      best = ref;
      bestIdx = h.idx;
    }
  }
  return best;
}

bool _isTensWord(int? n) => n != null && n >= 20 && n % 10 == 0;

bool _isOnesWord(int? n) => n != null && n >= 1 && n <= 9;

/// How many numeric values remain in [tokens] from [from] (tens+ones count as one).
int _countNumericValuesAhead(
  List<String> tokens,
  int from,
  Map<String, int> words,
) {
  var count = 0;
  var i = from;
  while (i < tokens.length) {
    if (int.tryParse(tokens[i]) != null) {
      count += 1;
      i += 1;
      continue;
    }
    final n = words[tokens[i]];
    if (n == null) {
      i += 1;
      continue;
    }
    if (_isTensWord(n) &&
        i + 1 < tokens.length &&
        _isOnesWord(words[tokens[i + 1]])) {
      count += 1;
      i += 2;
      continue;
    }
    count += 1;
    i += 1;
  }
  return count;
}

String? _chapterVerseFromRest(
  String rest,
  String book,
  Map<String, int> words,
) {
  final tokens =
      rest.trim().split(RegExp(r'\s+')).where((t) => t.isNotEmpty).toList();
  if (tokens.isEmpty) return null;

  int? parseNum(List<String> parts) {
    if (parts.isEmpty) return null;
    var total = 0;
    var current = 0;
    for (final t in parts) {
      final asDigit = int.tryParse(t);
      if (asDigit != null) {
        if (current != 0) {
          total += current;
          current = 0;
        }
        total += asDigit;
        continue;
      }
      final n = words[t];
      if (n == null) return null;
      if (n >= 20 && n % 10 == 0) {
        current += n;
      } else {
        current += n;
        total += current;
        current = 0;
      }
    }
    total += current;
    return total > 0 ? total : null;
  }

  final nums = <int>[];
  var i = 0;
  while (i < tokens.length && nums.length < 3) {
    final digit = int.tryParse(tokens[i]);
    if (digit != null) {
      nums.add(digit);
      i += 1;
      continue;
    }
    final one = parseNum([tokens[i]]);
    if (one == null) {
      i += 1;
      continue;
    }
    if (i + 1 < tokens.length && words.containsKey(tokens[i + 1])) {
      final tens = words[tokens[i]];
      final ones = words[tokens[i + 1]];
      if (_isTensWord(tens) && _isOnesWord(ones)) {
        // Prefer chapter:verse when two word-numbers follow a book with nothing
        // after ("thirty one" → 30:1). Once a chapter is set, compound for the
        // verse ("twenty three thirty one" → 23:31).
        final moreAfter = _countNumericValuesAhead(tokens, i + 2, words);
        if (nums.isEmpty && moreAfter == 0) {
          nums.add(tens!);
          nums.add(ones!);
          i += 2;
          continue;
        }
        final two = parseNum([tokens[i], tokens[i + 1]]);
        if (two != null) {
          nums.add(two);
          i += 2;
          continue;
        }
      }
    }
    // Prefer "three sixteen" as 3 then 16 (not 19).
    nums.add(one);
    i += 1;
  }

  if (nums.length < 2) return null;
  final chapter = nums[0];
  final verse = nums[1];
  // Only a real verse range (16-17). Restated chapter digits ("…1:7 … chapter 1")
  // must not become "1:7-1".
  if (nums.length >= 3 && nums[2] > verse) {
    return '$book $chapter:$verse-${nums[2]}';
  }
  return '$book $chapter:$verse';
}

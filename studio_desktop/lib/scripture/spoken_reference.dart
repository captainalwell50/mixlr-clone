/// Parse a spoken (or typed-from-STT) Bible reference into canonical form.
///
/// Handles common church cues: "John 3:16", "John three sixteen",
/// "chapter 3 verse 16", "first John 1:9", "1 John 1 9".
String? parseSpokenReference(String transcript) {
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
      .replaceAll(RegExp(r'\band\b'), ' ')
      .replaceAll(RegExp(r'\bthrough\b'), ' ')
      .replaceAll(RegExp(r'\bto\b'), ' ');

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

  String? book;
  var rest = text;
  final sorted = books.keys.toList()..sort((a, b) => b.length.compareTo(a.length));
  for (final spoken in sorted) {
    final needle = ' $spoken ';
    final idx = text.indexOf(needle);
    if (idx == -1) continue;
    book = books[spoken];
    rest = text.substring(idx + needle.length);
    break;
  }
  if (book == null) return null;

  final tokens = rest.trim().split(RegExp(r'\s+')).where((t) => t.isNotEmpty).toList();
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

  // Flatten mixed digit/word tokens into chapter + verse(+end).
  final nums = <int>[];
  var i = 0;
  while (i < tokens.length && nums.length < 3) {
    final digit = int.tryParse(tokens[i]);
    if (digit != null) {
      nums.add(digit);
      i += 1;
      continue;
    }
    // Take up to two word-number tokens for compound verses ("twenty three").
    final one = parseNum([tokens[i]]);
    if (one == null) {
      i += 1;
      continue;
    }
    if (i + 1 < tokens.length && words.containsKey(tokens[i + 1])) {
      final two = parseNum([tokens[i], tokens[i + 1]]);
      if (two != null && words[tokens[i]] != null && words[tokens[i]]! >= 20) {
        nums.add(two);
        i += 2;
        continue;
      }
    }
    // Prefer "three sixteen" as 3 then 16 (not 19).
    nums.add(one);
    i += 1;
  }

  if (nums.length < 2) return null;
  final chapter = nums[0];
  final verse = nums[1];
  if (nums.length >= 3 && nums[2] != verse) {
    return '$book $chapter:$verse-${nums[2]}';
  }
  return '$book $chapter:$verse';
}

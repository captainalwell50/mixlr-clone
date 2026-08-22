/// EasyWorship-style book completion for the Studio scripture box.
class BookCompletion {
  const BookCompletion({
    required this.book,
    required this.unique,
    required this.ghostSuffix,
    required this.expanded,
  });

  final String book;
  final bool unique;
  final String ghostSuffix;

  /// Value to write when the completion is accepted (`Ecclesiastes ` or `Ecclesiastes 3:16`).
  final String expanded;
}

const List<String> canonBooks = [
  'Genesis',
  'Exodus',
  'Leviticus',
  'Numbers',
  'Deuteronomy',
  'Joshua',
  'Judges',
  'Ruth',
  '1 Samuel',
  '2 Samuel',
  '1 Kings',
  '2 Kings',
  '1 Chronicles',
  '2 Chronicles',
  'Ezra',
  'Nehemiah',
  'Esther',
  'Job',
  'Psalms',
  'Proverbs',
  'Ecclesiastes',
  'Song of Solomon',
  'Isaiah',
  'Jeremiah',
  'Lamentations',
  'Ezekiel',
  'Daniel',
  'Hosea',
  'Joel',
  'Amos',
  'Obadiah',
  'Jonah',
  'Micah',
  'Nahum',
  'Habakkuk',
  'Zephaniah',
  'Haggai',
  'Zechariah',
  'Malachi',
  'Matthew',
  'Mark',
  'Luke',
  'John',
  'Acts',
  'Romans',
  '1 Corinthians',
  '2 Corinthians',
  'Galatians',
  'Ephesians',
  'Philippians',
  'Colossians',
  '1 Thessalonians',
  '2 Thessalonians',
  '1 Timothy',
  '2 Timothy',
  'Titus',
  'Philemon',
  'Hebrews',
  'James',
  '1 Peter',
  '2 Peter',
  '1 John',
  '2 John',
  '3 John',
  'Jude',
  'Revelation',
];

const Map<String, String> bookAliases = {
  'gen': 'Genesis',
  'gn': 'Genesis',
  'ge': 'Genesis',
  'genesis': 'Genesis',
  'ex': 'Exodus',
  'exo': 'Exodus',
  'exod': 'Exodus',
  'exodus': 'Exodus',
  'lev': 'Leviticus',
  'le': 'Leviticus',
  'lv': 'Leviticus',
  'leviticus': 'Leviticus',
  'num': 'Numbers',
  'nu': 'Numbers',
  'nm': 'Numbers',
  'numbers': 'Numbers',
  'deut': 'Deuteronomy',
  'dt': 'Deuteronomy',
  'de': 'Deuteronomy',
  'deuteronomy': 'Deuteronomy',
  'josh': 'Joshua',
  'jos': 'Joshua',
  'joshua': 'Joshua',
  'judg': 'Judges',
  'jdg': 'Judges',
  'jg': 'Judges',
  'judges': 'Judges',
  'ruth': 'Ruth',
  'ru': 'Ruth',
  '1sam': '1 Samuel',
  '1 samuel': '1 Samuel',
  '1sa': '1 Samuel',
  '2sam': '2 Samuel',
  '2 samuel': '2 Samuel',
  '2sa': '2 Samuel',
  '1kgs': '1 Kings',
  '1 kings': '1 Kings',
  '1ki': '1 Kings',
  '2kgs': '2 Kings',
  '2 kings': '2 Kings',
  '2ki': '2 Kings',
  '1chr': '1 Chronicles',
  '1 chronicles': '1 Chronicles',
  '1ch': '1 Chronicles',
  '2chr': '2 Chronicles',
  '2 chronicles': '2 Chronicles',
  '2ch': '2 Chronicles',
  'ezra': 'Ezra',
  'ezr': 'Ezra',
  'neh': 'Nehemiah',
  'ne': 'Nehemiah',
  'nehemiah': 'Nehemiah',
  'esth': 'Esther',
  'est': 'Esther',
  'esther': 'Esther',
  'job': 'Job',
  'ps': 'Psalms',
  'psa': 'Psalms',
  'psalm': 'Psalms',
  'psalms': 'Psalms',
  'prov': 'Proverbs',
  'pr': 'Proverbs',
  'prv': 'Proverbs',
  'proverbs': 'Proverbs',
  'eccl': 'Ecclesiastes',
  'ecc': 'Ecclesiastes',
  'ec': 'Ecclesiastes',
  'ecclesiastes': 'Ecclesiastes',
  'song': 'Song of Solomon',
  'sos': 'Song of Solomon',
  'ss': 'Song of Solomon',
  'song of solomon': 'Song of Solomon',
  'song of songs': 'Song of Solomon',
  'isa': 'Isaiah',
  'is': 'Isaiah',
  'isaiah': 'Isaiah',
  'jer': 'Jeremiah',
  'je': 'Jeremiah',
  'jeremiah': 'Jeremiah',
  'lam': 'Lamentations',
  'la': 'Lamentations',
  'lamentations': 'Lamentations',
  'ezek': 'Ezekiel',
  'eze': 'Ezekiel',
  'ezk': 'Ezekiel',
  'ezekiel': 'Ezekiel',
  'dan': 'Daniel',
  'da': 'Daniel',
  'dn': 'Daniel',
  'daniel': 'Daniel',
  'hos': 'Hosea',
  'ho': 'Hosea',
  'hosea': 'Hosea',
  'joel': 'Joel',
  'jl': 'Joel',
  'amos': 'Amos',
  'am': 'Amos',
  'obad': 'Obadiah',
  'ob': 'Obadiah',
  'obadiah': 'Obadiah',
  'jonah': 'Jonah',
  'jon': 'Jonah',
  'jnh': 'Jonah',
  'mic': 'Micah',
  'mi': 'Micah',
  'micah': 'Micah',
  'nah': 'Nahum',
  'na': 'Nahum',
  'nahum': 'Nahum',
  'hab': 'Habakkuk',
  'habakkuk': 'Habakkuk',
  'zeph': 'Zephaniah',
  'zep': 'Zephaniah',
  'zephaniah': 'Zephaniah',
  'hag': 'Haggai',
  'haggai': 'Haggai',
  'zech': 'Zechariah',
  'zec': 'Zechariah',
  'zechariah': 'Zechariah',
  'mal': 'Malachi',
  'malachi': 'Malachi',
  'matt': 'Matthew',
  'mt': 'Matthew',
  'mat': 'Matthew',
  'matthew': 'Matthew',
  'mark': 'Mark',
  'mk': 'Mark',
  'mr': 'Mark',
  'luke': 'Luke',
  'lk': 'Luke',
  'lu': 'Luke',
  'john': 'John',
  'jn': 'John',
  'joh': 'John',
  'acts': 'Acts',
  'ac': 'Acts',
  'rom': 'Romans',
  'ro': 'Romans',
  'romans': 'Romans',
  '1cor': '1 Corinthians',
  '1 corinthians': '1 Corinthians',
  '1co': '1 Corinthians',
  '2cor': '2 Corinthians',
  '2 corinthians': '2 Corinthians',
  '2co': '2 Corinthians',
  'gal': 'Galatians',
  'ga': 'Galatians',
  'galatians': 'Galatians',
  'eph': 'Ephesians',
  'ephesians': 'Ephesians',
  'phil': 'Philippians',
  'php': 'Philippians',
  'philippians': 'Philippians',
  'col': 'Colossians',
  'colossians': 'Colossians',
  '1thess': '1 Thessalonians',
  '1 thessalonians': '1 Thessalonians',
  '1th': '1 Thessalonians',
  '2thess': '2 Thessalonians',
  '2 thessalonians': '2 Thessalonians',
  '2th': '2 Thessalonians',
  '1tim': '1 Timothy',
  '1 timothy': '1 Timothy',
  '1ti': '1 Timothy',
  '2tim': '2 Timothy',
  '2 timothy': '2 Timothy',
  '2ti': '2 Timothy',
  'tit': 'Titus',
  'ti': 'Titus',
  'titus': 'Titus',
  'phlm': 'Philemon',
  'phm': 'Philemon',
  'philemon': 'Philemon',
  'heb': 'Hebrews',
  'hebrews': 'Hebrews',
  'jas': 'James',
  'jm': 'James',
  'james': 'James',
  '1pet': '1 Peter',
  '1 peter': '1 Peter',
  '1pe': '1 Peter',
  '2pet': '2 Peter',
  '2 peter': '2 Peter',
  '2pe': '2 Peter',
  '1john': '1 John',
  '1 john': '1 John',
  '1jn': '1 John',
  '2john': '2 John',
  '2 john': '2 John',
  '2jn': '2 John',
  '3john': '3 John',
  '3 john': '3 John',
  '3jn': '3 John',
  'jude': 'Jude',
  'rev': 'Revelation',
  're': 'Revelation',
  'revelation': 'Revelation',
  'revelations': 'Revelation',
};

({String bookToken, String separator, String? rest}) splitScriptureQuery(String raw) {
  final value = raw.replaceFirst(RegExp(r'^\s+'), '');
  final withRest = RegExp(r'^(.+?)(\s+)(\d.*)$').firstMatch(value);
  if (withRest != null) {
    return (
      bookToken: withRest.group(1)!,
      separator: withRest.group(2)!,
      rest: withRest.group(3)!,
    );
  }
  final trailing = RegExp(r'^(.*\S)(\s+)$').firstMatch(value);
  if (trailing != null) {
    return (bookToken: trailing.group(1)!, separator: trailing.group(2)!, rest: '');
  }
  return (bookToken: value, separator: '', rest: null);
}

String normalizeBookToken(String token) {
  var key = token.toLowerCase().trim().replaceAll('.', '');
  key = key.replaceAll(RegExp(r'\s+'), ' ');
  key = key.replaceFirst(RegExp(r'^iii\s+'), '3 ');
  key = key.replaceFirst(RegExp(r'^ii\s+'), '2 ');
  key = key.replaceFirst(RegExp(r'^i\s+'), '1 ');
  key = key.replaceFirst(RegExp(r'^3rd\s+'), '3 ');
  key = key.replaceFirst(RegExp(r'^2nd\s+'), '2 ');
  key = key.replaceFirst(RegExp(r'^1st\s+'), '1 ');
  return key;
}

List<String> matchBooks(String token) {
  final key = normalizeBookToken(token);
  if (key.isEmpty) return const [];
  final compact = key.replaceAll(' ', '');
  final found = <String>[];
  final seen = <String>{};

  void add(String book) {
    if (seen.add(book)) found.add(book);
  }

  final exact = bookAliases[key] ?? bookAliases[compact];
  if (exact != null) add(exact);

  for (final book in canonBooks) {
    final lower = book.toLowerCase();
    final bookCompact = lower.replaceAll(' ', '');
    if (lower == key ||
        bookCompact == compact ||
        lower.startsWith(key) ||
        bookCompact.startsWith(compact)) {
      add(book);
    }
  }
  return found;
}

BookCompletion? inferBookCompletion(String raw) {
  final parts = splitScriptureQuery(raw);
  if (parts.bookToken.isEmpty) return null;
  final matches = matchBooks(parts.bookToken);
  if (matches.isEmpty) return null;
  final book = matches.first;
  final unique = matches.length == 1;
  final expanded = parts.rest != null
      ? '$book${parts.separator}${parts.rest}'
      : '$book ';
  var ghostSuffix = '';
  if (book.toLowerCase().startsWith(parts.bookToken.toLowerCase())) {
    ghostSuffix = book.substring(parts.bookToken.length);
  }
  return BookCompletion(
    book: book,
    unique: unique,
    ghostSuffix: ghostSuffix,
    expanded: expanded,
  );
}

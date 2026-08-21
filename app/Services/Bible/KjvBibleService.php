<?php

namespace App\Services\Bible;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KjvBibleService
{
    /** @var array<string, string> lowercase alias → canonical book name */
    private const ALIASES = [
        'gen' => 'Genesis', 'gn' => 'Genesis', 'ge' => 'Genesis', 'genesis' => 'Genesis',
        'ex' => 'Exodus', 'exo' => 'Exodus', 'exod' => 'Exodus', 'exodus' => 'Exodus',
        'lev' => 'Leviticus', 'le' => 'Leviticus', 'lv' => 'Leviticus', 'leviticus' => 'Leviticus',
        'num' => 'Numbers', 'nu' => 'Numbers', 'nm' => 'Numbers', 'numbers' => 'Numbers',
        'deut' => 'Deuteronomy', 'dt' => 'Deuteronomy', 'de' => 'Deuteronomy', 'deuteronomy' => 'Deuteronomy',
        'josh' => 'Joshua', 'jos' => 'Joshua', 'joshua' => 'Joshua',
        'judg' => 'Judges', 'jdg' => 'Judges', 'jg' => 'Judges', 'judges' => 'Judges',
        'ruth' => 'Ruth', 'ru' => 'Ruth',
        '1sam' => '1 Samuel', '1 samuel' => '1 Samuel', '1sa' => '1 Samuel', 'i samuel' => '1 Samuel', 'first samuel' => '1 Samuel',
        '2sam' => '2 Samuel', '2 samuel' => '2 Samuel', '2sa' => '2 Samuel', 'ii samuel' => '2 Samuel', 'second samuel' => '2 Samuel',
        '1kgs' => '1 Kings', '1 kings' => '1 Kings', '1ki' => '1 Kings', 'i kings' => '1 Kings', 'first kings' => '1 Kings',
        '2kgs' => '2 Kings', '2 kings' => '2 Kings', '2ki' => '2 Kings', 'ii kings' => '2 Kings', 'second kings' => '2 Kings',
        '1chr' => '1 Chronicles', '1 chronicles' => '1 Chronicles', '1ch' => '1 Chronicles', 'i chronicles' => '1 Chronicles', 'first chronicles' => '1 Chronicles',
        '2chr' => '2 Chronicles', '2 chronicles' => '2 Chronicles', '2ch' => '2 Chronicles', 'ii chronicles' => '2 Chronicles', 'second chronicles' => '2 Chronicles',
        'ezra' => 'Ezra', 'ezr' => 'Ezra',
        'neh' => 'Nehemiah', 'ne' => 'Nehemiah', 'nehemiah' => 'Nehemiah',
        'esth' => 'Esther', 'est' => 'Esther', 'esther' => 'Esther',
        'job' => 'Job',
        'ps' => 'Psalms', 'psa' => 'Psalms', 'psalm' => 'Psalms', 'psalms' => 'Psalms',
        'prov' => 'Proverbs', 'pr' => 'Proverbs', 'prv' => 'Proverbs', 'proverbs' => 'Proverbs',
        'eccl' => 'Ecclesiastes', 'ecc' => 'Ecclesiastes', 'ec' => 'Ecclesiastes', 'ecclesiastes' => 'Ecclesiastes',
        'song' => 'Song of Solomon', 'sos' => 'Song of Solomon', 'ss' => 'Song of Solomon', 'song of solomon' => 'Song of Solomon', 'song of songs' => 'Song of Solomon',
        'isa' => 'Isaiah', 'is' => 'Isaiah', 'isaiah' => 'Isaiah',
        'jer' => 'Jeremiah', 'je' => 'Jeremiah', 'jeremiah' => 'Jeremiah',
        'lam' => 'Lamentations', 'la' => 'Lamentations', 'lamentations' => 'Lamentations',
        'ezek' => 'Ezekiel', 'eze' => 'Ezekiel', 'ezk' => 'Ezekiel', 'ezekiel' => 'Ezekiel',
        'dan' => 'Daniel', 'da' => 'Daniel', 'dn' => 'Daniel', 'daniel' => 'Daniel',
        'hos' => 'Hosea', 'ho' => 'Hosea', 'hosea' => 'Hosea',
        'joel' => 'Joel', 'jl' => 'Joel',
        'amos' => 'Amos', 'am' => 'Amos',
        'obad' => 'Obadiah', 'ob' => 'Obadiah', 'obadiah' => 'Obadiah',
        'jonah' => 'Jonah', 'jon' => 'Jonah', 'jnh' => 'Jonah',
        'mic' => 'Micah', 'mi' => 'Micah', 'micah' => 'Micah',
        'nah' => 'Nahum', 'na' => 'Nahum', 'nahum' => 'Nahum',
        'hab' => 'Habakkuk', 'habakkuk' => 'Habakkuk',
        'zeph' => 'Zephaniah', 'zep' => 'Zephaniah', 'zephaniah' => 'Zephaniah',
        'hag' => 'Haggai', 'haggai' => 'Haggai',
        'zech' => 'Zechariah', 'zec' => 'Zechariah', 'zechariah' => 'Zechariah',
        'mal' => 'Malachi', 'malachi' => 'Malachi',
        'matt' => 'Matthew', 'mt' => 'Matthew', 'mat' => 'Matthew', 'matthew' => 'Matthew',
        'mark' => 'Mark', 'mk' => 'Mark', 'mr' => 'Mark',
        'luke' => 'Luke', 'lk' => 'Luke', 'lu' => 'Luke',
        'john' => 'John', 'jn' => 'John', 'joh' => 'John',
        'acts' => 'Acts', 'ac' => 'Acts',
        'rom' => 'Romans', 'ro' => 'Romans', 'romans' => 'Romans',
        '1cor' => '1 Corinthians', '1 corinthians' => '1 Corinthians', '1co' => '1 Corinthians', 'i corinthians' => '1 Corinthians', 'first corinthians' => '1 Corinthians',
        '2cor' => '2 Corinthians', '2 corinthians' => '2 Corinthians', '2co' => '2 Corinthians', 'ii corinthians' => '2 Corinthians', 'second corinthians' => '2 Corinthians',
        'gal' => 'Galatians', 'ga' => 'Galatians', 'galatians' => 'Galatians',
        'eph' => 'Ephesians', 'ephesians' => 'Ephesians',
        'phil' => 'Philippians', 'php' => 'Philippians', 'philippians' => 'Philippians',
        'col' => 'Colossians', 'colossians' => 'Colossians',
        '1thess' => '1 Thessalonians', '1 thessalonians' => '1 Thessalonians', '1th' => '1 Thessalonians', 'i thessalonians' => '1 Thessalonians', 'first thessalonians' => '1 Thessalonians',
        '2thess' => '2 Thessalonians', '2 thessalonians' => '2 Thessalonians', '2th' => '2 Thessalonians', 'ii thessalonians' => '2 Thessalonians', 'second thessalonians' => '2 Thessalonians',
        '1tim' => '1 Timothy', '1 timothy' => '1 Timothy', '1ti' => '1 Timothy', 'i timothy' => '1 Timothy', 'first timothy' => '1 Timothy',
        '2tim' => '2 Timothy', '2 timothy' => '2 Timothy', '2ti' => '2 Timothy', 'ii timothy' => '2 Timothy', 'second timothy' => '2 Timothy',
        'tit' => 'Titus', 'ti' => 'Titus', 'titus' => 'Titus',
        'phlm' => 'Philemon', 'phm' => 'Philemon', 'philemon' => 'Philemon',
        'heb' => 'Hebrews', 'hebrews' => 'Hebrews',
        'jas' => 'James', 'jm' => 'James', 'james' => 'James',
        '1pet' => '1 Peter', '1 peter' => '1 Peter', '1pe' => '1 Peter', 'i peter' => '1 Peter', 'first peter' => '1 Peter',
        '2pet' => '2 Peter', '2 peter' => '2 Peter', '2pe' => '2 Peter', 'ii peter' => '2 Peter', 'second peter' => '2 Peter',
        '1john' => '1 John', '1 john' => '1 John', '1jn' => '1 John', 'i john' => '1 John', 'first john' => '1 John',
        '2john' => '2 John', '2 john' => '2 John', '2jn' => '2 John', 'ii john' => '2 John', 'second john' => '2 John',
        '3john' => '3 John', '3 john' => '3 John', '3jn' => '3 John', 'iii john' => '3 John', 'third john' => '3 John',
        'jude' => 'Jude',
        'rev' => 'Revelation', 're' => 'Revelation', 'revelation' => 'Revelation', 'revelations' => 'Revelation',
    ];

    /**
     * @return array{ref: string, text: string}|null
     */
    public function resolve(string $ref): ?array
    {
        $parsed = $this->parseReference($ref);
        if ($parsed === null) {
            return null;
        }

        [$book, $chapter, $verseStart, $verseEnd] = $parsed;
        $bookData = $this->bookByName($book);
        if ($bookData === null) {
            return null;
        }

        $chapters = $bookData['chapters'];
        if ($chapter < 1 || $chapter > count($chapters)) {
            return null;
        }

        $verses = $chapters[$chapter - 1];
        $max = count($verses);
        if ($verseStart < 1 || $verseStart > $max) {
            return null;
        }
        $verseEnd = min($verseEnd, $max);
        if ($verseEnd < $verseStart) {
            return null;
        }

        $parts = [];
        for ($v = $verseStart; $v <= $verseEnd; $v++) {
            $parts[] = trim((string) $verses[$v - 1]);
        }

        $displayRef = $verseStart === $verseEnd
            ? sprintf('%s %d:%d', $book, $chapter, $verseStart)
            : sprintf('%s %d:%d-%d', $book, $chapter, $verseStart, $verseEnd);

        return [
            'ref' => $displayRef,
            'text' => implode(' ', $parts),
        ];
    }

    /**
     * Suggest book names / chapter:verse completions for Studio autocomplete.
     *
     * @return list<array{ref: string, preview: string}>
     */
    public function suggest(string $query, int $limit = 12): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        if ($query === '') {
            return [];
        }

        $resolved = $this->resolve($query);
        if ($resolved !== null) {
            return [[
                'ref' => $resolved['ref'],
                'preview' => Str::limit($resolved['text'], 120),
            ]];
        }

        // "John 3", "John 3:", "Jn 3 :" → chapter verse list
        if (preg_match('/^(?P<book>.+?)\s+(?P<chapter>\d+)\s*:?\s*$/u', $query, $m)) {
            $chapterSuggestions = $this->suggestChapterVerses(
                (string) $m['book'],
                (int) $m['chapter'],
                $limit,
            );
            if ($chapterSuggestions !== []) {
                return $chapterSuggestions;
            }
        }

        // "John 3:1-" → offer single verse + short ranges from that start
        if (preg_match(
            '/^(?P<book>.+?)\s+(?P<chapter>\d+)\s*:\s*(?P<start>\d+)\s*[-–—]\s*$/u',
            $query,
            $m
        )) {
            $rangeSuggestions = $this->suggestFromVerseStart(
                (string) $m['book'],
                (int) $m['chapter'],
                (int) $m['start'],
                $limit,
            );
            if ($rangeSuggestions !== []) {
                return $rangeSuggestions;
            }
        }

        // "John 3:1" invalid (out of range) already returned []; fall through to books
        $q = Str::lower($query);
        $aliasBook = $this->normalizeBookName($query);
        $out = [];

        foreach ($this->books() as $book) {
            $name = (string) $book['name'];
            $abbrev = Str::lower((string) $book['abbrev']);
            $nameLower = Str::lower($name);
            $matched = $aliasBook === $name
                || str_starts_with($nameLower, $q)
                || str_contains($nameLower, $q)
                || str_starts_with($abbrev, $q)
                || str_contains($abbrev, $q);

            if (! $matched) {
                continue;
            }

            $first = $book['chapters'][0][0] ?? '';
            $out[] = [
                'ref' => $name.' 1:1',
                'preview' => Str::limit((string) $first, 120),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        // Prefer prefix / alias hits first (already roughly ordered by canon).
        if ($aliasBook !== null) {
            usort($out, function (array $a, array $b) use ($aliasBook): int {
                $aExact = str_starts_with($a['ref'], $aliasBook.' ') ? 0 : 1;
                $bExact = str_starts_with($b['ref'], $aliasBook.' ') ? 0 : 1;

                return $aExact <=> $bExact;
            });
        }

        return array_values($out);
    }

    /**
     * @return list<array{ref: string, preview: string}>
     */
    private function suggestChapterVerses(string $bookInput, int $chapter, int $limit): array
    {
        $book = $this->normalizeBookName($bookInput);
        if ($book === null) {
            return [];
        }

        $bookData = $this->bookByName($book);
        if ($bookData === null || $chapter < 1 || $chapter > count($bookData['chapters'])) {
            return [];
        }

        $verses = $bookData['chapters'][$chapter - 1];
        $out = [];
        $max = min(count($verses), max(1, $limit));
        for ($v = 1; $v <= $max; $v++) {
            $out[] = [
                'ref' => sprintf('%s %d:%d', $book, $chapter, $v),
                'preview' => Str::limit((string) $verses[$v - 1], 120),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{ref: string, preview: string}>
     */
    private function suggestFromVerseStart(string $bookInput, int $chapter, int $start, int $limit): array
    {
        $book = $this->normalizeBookName($bookInput);
        if ($book === null) {
            return [];
        }

        $bookData = $this->bookByName($book);
        if ($bookData === null || $chapter < 1 || $chapter > count($bookData['chapters'])) {
            return [];
        }

        $verses = $bookData['chapters'][$chapter - 1];
        $max = count($verses);
        if ($start < 1 || $start > $max) {
            return [];
        }

        $out = [];
        $single = $this->resolve(sprintf('%s %d:%d', $book, $chapter, $start));
        if ($single !== null) {
            $out[] = [
                'ref' => $single['ref'],
                'preview' => Str::limit($single['text'], 120),
            ];
        }

        foreach ([2, 3, 5] as $span) {
            $end = min($start + $span - 1, $max);
            if ($end <= $start) {
                continue;
            }
            $range = $this->resolve(sprintf('%s %d:%d-%d', $book, $chapter, $start, $end));
            if ($range === null) {
                continue;
            }
            $out[] = [
                'ref' => $range['ref'],
                'preview' => Str::limit($range['text'], 120),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int}|null book, chapter, verseStart, verseEnd
     */
    public function parseReference(string $raw): ?array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($raw === '') {
            return null;
        }

        // "John 3:16", "1 Cor 13:4-7", "Jn 3:16–18"
        if (! preg_match(
            '/^(?P<book>.+?)\s+(?P<chapter>\d+)\s*:\s*(?P<start>\d+)(?:\s*[-–—]\s*(?P<end>\d+))?$/u',
            $raw,
            $m
        )) {
            return null;
        }

        $book = $this->normalizeBookName($m['book']);
        if ($book === null) {
            return null;
        }

        $chapter = (int) $m['chapter'];
        $start = (int) $m['start'];
        $end = isset($m['end']) && $m['end'] !== '' ? (int) $m['end'] : $start;

        return [$book, $chapter, $start, $end];
    }

    public function normalizeBookName(string $input): ?string
    {
        $key = Str::lower(trim($input));
        $key = preg_replace('/\./', '', $key) ?? $key;
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        // "1 Corinthians" / "I Corinthians"
        $key = preg_replace('/^i\s+/', '1 ', $key) ?? $key;
        $key = preg_replace('/^ii\s+/', '2 ', $key) ?? $key;
        $key = preg_replace('/^iii\s+/', '3 ', $key) ?? $key;
        $key = preg_replace('/^1st\s+/', '1 ', $key) ?? $key;
        $key = preg_replace('/^2nd\s+/', '2 ', $key) ?? $key;
        $key = preg_replace('/^3rd\s+/', '3 ', $key) ?? $key;

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Compact "1corinthians"
        $compact = str_replace(' ', '', $key);
        if (isset(self::ALIASES[$compact])) {
            return self::ALIASES[$compact];
        }

        foreach ($this->books() as $book) {
            if (Str::lower((string) $book['name']) === $key) {
                return (string) $book['name'];
            }
        }

        return null;
    }

    /**
     * @return list<array{abbrev: string, name: string, chapters: list<list<string>>}>
     */
    public function books(): array
    {
        return Cache::remember('bible:kjv:books', now()->addDay(), function (): array {
            $path = database_path('data/bible/en_kjv.json');
            if (! is_readable($path)) {
                return [];
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                return [];
            }
            /** @var list<array{abbrev: string, name: string, chapters: list<list<string>>}>|null $data */
            $data = json_decode($raw, true);

            return is_array($data) ? $data : [];
        });
    }

    /**
     * @return array{abbrev: string, name: string, chapters: list<list<string>>}|null
     */
    private function bookByName(string $name): ?array
    {
        foreach ($this->books() as $book) {
            if ((string) $book['name'] === $name) {
                return $book;
            }
        }

        return null;
    }
}

# KJV Bible data

`en_kjv.json` is the public-domain King James Version used by Sound Mix Live scripture display.

Source format: list of books with `name`, `abbrev`, and `chapters` (arrays of verse strings).

Do not remove this file from deploys — `App\Services\Bible\KjvBibleService` loads it at runtime.

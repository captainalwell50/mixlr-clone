<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject obvious random/gibberish display names while allowing normal names
 * and short titles like "Church admin".
 */
class RealPersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($name === '' || mb_strlen($name) < 2) {
            $fail('Please enter a real name.');

            return;
        }

        if (mb_strlen($name) > 80) {
            $fail('Please use a shorter name.');

            return;
        }

        // Letters (any script), marks, spaces, hyphen, apostrophe, period.
        if (! preg_match("/^[\p{L}\p{M}][\p{L}\p{M}'\.\-]*(?: [\p{L}\p{M}][\p{L}\p{M}'\.\-]*)*$/u", $name)) {
            $fail('Please enter a real name using letters (numbers and symbols are not allowed).');

            return;
        }

        if (preg_match('/(.)\1{3,}/u', $name)) {
            $fail('Please enter a real name.');

            return;
        }

        $hasSpace = str_contains($name, ' ');

        // Long single-token names are a common bot pattern.
        if (! $hasSpace && mb_strlen($name) > 20) {
            $fail('Please enter your full name (first and last), or a shorter display name.');

            return;
        }

        // Latin-only single tokens: require a vowel and reject keyboard-mash density.
        if (! $hasSpace && preg_match('/^[A-Za-z\'\.\-]+$/', $name) && mb_strlen($name) >= 8) {
            if (! preg_match('/[aeiouyAEIOUY]/', $name)) {
                $fail('Please enter a real name.');

                return;
            }

            if ($this->looksLikeRandomLatin($name)) {
                $fail('Please enter a real name.');

                return;
            }
        }
    }

    private function looksLikeRandomLatin(string $name): bool
    {
        $letters = preg_replace("/[^A-Za-z]/", '', $name) ?? '';
        $len = strlen($letters);

        if ($len < 8) {
            return false;
        }

        // Alternating case mid-string (aBcDeFgH) is uncommon for real names.
        if (preg_match('/[a-z][A-Z][a-z][A-Z]/', $letters) || preg_match('/[A-Z][a-z][A-Z][a-z][A-Z]/', $letters)) {
            return true;
        }

        // High consonant-cluster density without vowels nearby.
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $letters)) {
            return true;
        }

        // Very few unique letters relative to length (e.g. "aaaaaaab").
        $unique = count(array_unique(str_split(strtolower($letters))));

        return $unique <= 3 && $len >= 8;
    }
}

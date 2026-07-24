<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lightweight disposable-domain blocklist. Does not target Gmail/Yahoo/etc.
 */
class NotDisposableEmail implements ValidationRule
{
    /** @var list<string> */
    private const DOMAINS = [
        'mailinator.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'sharklasers.com',
        'grr.la',
        'yopmail.com',
        'tempmail.com',
        'temp-mail.org',
        '10minutemail.com',
        'trashmail.com',
        'trashmail.me',
        'discard.email',
        'mailnesia.com',
        'maildrop.cc',
        'getnada.com',
        'nada.email',
        'fakeinbox.com',
        'throwaway.email',
        'tempail.com',
        'moakt.com',
        'dispostable.com',
        'mailcatch.com',
        'mytemp.email',
        'emailondeck.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('registration.block_disposable_emails')) {
            return;
        }

        $email = strtolower(trim((string) $value));
        $domain = substr(strrchr($email, '@') ?: '', 1);

        if ($domain === '' || $domain === false) {
            return;
        }

        foreach (self::DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                $fail('Please use a permanent email address.');

                return;
            }
        }
    }
}

<?php

namespace App\Support;

use App\Models\SiteSetting;

class WhatsAppSupport
{
    /**
     * Determine if WhatsApp support is enabled and configured.
     */
    public static function isEnabled(): bool
    {
        return SiteSetting::whatsappSupportEnabled() && filled(self::number());
    }

    /**
     * Get the authoritative normalized WhatsApp phone number (digits only, e.g. 2348000000000).
     */
    public static function number(): ?string
    {
        return SiteSetting::whatsappSupportNumber();
    }

    /**
     * Build a safe, context-aware prefilled message for the user.
     *
     * Never leaks stream keys, auth tokens, capability URLs, private credentials, or payment data.
     */
    public static function buildMessage(
        string $context = 'public',
        ?string $pageLabel = null,
        ?string $organisation = null,
        ?string $eventTitle = null,
        ?string $status = null
    ): string {
        $cleanContext = strtolower(trim($context));

        // Default greeting
        $greeting = "Hello SoundMix Support,\nI need help with SoundMix.";

        $details = [];

        switch ($cleanContext) {
            case 'studio':
                $greeting = "Hello SoundMix Support,\nI need help with Web Studio.";
                if (filled($organisation)) {
                    $details[] = 'Organisation: ' . trim((string) $organisation);
                }
                if (filled($status)) {
                    $details[] = 'Broadcast status: ' . trim((string) $status);
                }
                if (filled($eventTitle)) {
                    $details[] = 'Event: ' . trim((string) $eventTitle);
                }
                break;

            case 'listener':
                $greeting = "Hello SoundMix Support,\nI need help with a SoundMix listening page.";
                if (filled($organisation)) {
                    $details[] = 'Channel: ' . trim((string) $organisation);
                }
                if (filled($eventTitle)) {
                    $details[] = 'Event: ' . trim((string) $eventTitle);
                }
                break;

            case 'creator':
            case 'dashboard':
                $greeting = "Hello SoundMix Support,\nI need help with my Creator account.";
                if (filled($organisation)) {
                    $details[] = 'Organisation: ' . trim((string) $organisation);
                }
                if (filled($pageLabel)) {
                    $details[] = 'Page: ' . trim((string) $pageLabel);
                }
                break;

            case 'admin':
                $greeting = "Hello SoundMix Support,\nI need help with the Admin Console.";
                if (filled($pageLabel)) {
                    $details[] = 'Section: ' . trim((string) $pageLabel);
                }
                break;

            case 'affiliate':
                $greeting = "Hello SoundMix Support,\nI need help with the Partner Programme.";
                if (filled($pageLabel)) {
                    $details[] = 'Page: ' . trim((string) $pageLabel);
                }
                break;

            case 'public':
            default:
                if (filled($pageLabel)) {
                    $details[] = 'Page: ' . trim((string) $pageLabel);
                }
                break;
        }

        if (empty($details)) {
            return $greeting;
        }

        return $greeting . "\n\n" . implode("\n", $details);
    }

    /**
     * Generate the complete wa.me URL with prefilled, URL-encoded message.
     */
    public static function url(
        string $context = 'public',
        ?string $pageLabel = null,
        ?string $organisation = null,
        ?string $eventTitle = null,
        ?string $status = null
    ): ?string {
        if (! self::isEnabled()) {
            return null;
        }

        $number = self::number();
        $message = self::buildMessage($context, $pageLabel, $organisation, $eventTitle, $status);

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }
}

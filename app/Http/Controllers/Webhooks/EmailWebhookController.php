<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Authenticate webhook request via configured token or shared secret
        $configuredSecret = config('services.email_webhook.secret', env('EMAIL_WEBHOOK_SECRET'));
        if (filled($configuredSecret)) {
            $headerToken = $request->header('X-Email-Webhook-Secret') ?? $request->header('X-Webhook-Token');
            $bearer = $request->bearerToken();
            $token = $headerToken ?? $bearer ?? $request->input('secret');

            if (! is_string($token) || ! hash_equals((string) $configuredSecret, (string) $token)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $event = strtolower((string) ($request->input('event') ?? $request->input('type') ?? ''));
        $recipient = (string) ($request->input('recipient') ?? $request->input('email') ?? '');
        $messageId = (string) ($request->input('provider_message_id') ?? $request->input('message_id') ?? '');
        $eventKey = (string) ($request->input('event_key') ?? '');
        $reason = (string) ($request->input('reason') ?? $request->input('description') ?? '');

        if ($recipient === '' && $messageId === '' && $eventKey === '') {
            return response()->json(['error' => 'Missing identifying parameters'], 422);
        }

        // Find relevant delivery ledger row
        $query = EmailDelivery::query();
        if ($messageId !== '') {
            $query->where('provider_message_id', $messageId);
        } elseif ($eventKey !== '') {
            $query->where('event_key', $eventKey);
        } elseif ($recipient !== '') {
            $query->where('recipient', $recipient)->latest();
        }

        $delivery = $query->first();

        match ($event) {
            'delivered', 'delivery' => $delivery?->markDelivered(),
            'bounced', 'bounce', 'hard_bounce' => $this->handleBounce($delivery, $recipient, $reason),
            'complained', 'complaint', 'spam' => $this->handleComplaint($delivery, $recipient),
            'rejected', 'dropped' => $delivery?->markFailed($reason !== '' ? $reason : 'Rejected by provider'),
            default => Log::info('Email webhook unhandled event', ['event' => $event, 'recipient' => $recipient]),
        };

        return response()->json(['status' => 'acknowledged']);
    }

    private function handleBounce(?EmailDelivery $delivery, string $recipient, string $reason): void
    {
        $delivery?->markBounced($reason);

        // Auto-suppress recipient from marketing communications on hard bounce
        if ($recipient !== '') {
            User::query()->where('email', $recipient)->each(function (User $user): void {
                $user->unsubscribeFromMarketing();
            });
        }
    }

    private function handleComplaint(?EmailDelivery $delivery, string $recipient): void
    {
        $delivery?->markComplained();

        // Immediately opt user out of marketing
        if ($recipient !== '') {
            User::query()->where('email', $recipient)->each(function (User $user): void {
                $user->unsubscribeFromMarketing();
            });
        }
    }
}

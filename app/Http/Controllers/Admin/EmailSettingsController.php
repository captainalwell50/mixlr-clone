<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailRoutingAudit;
use App\Models\SiteSetting;
use App\Services\CloudflareEmailRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    public const MANAGED_ALIASES = [
        'support@soundmix.live',
        'hello@soundmix.live',
        'billing@soundmix.live',
    ];

    public function index(Request $request, CloudflareEmailRoutingService $cloudflare): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $activeDestination = SiteSetting::emailRoutingActiveDestination();
        $cfConfigured = $cloudflare->isConfigured();

        if (! $activeDestination && $cfConfigured) {
            $cfDestination = $cloudflare->getActiveDestinationFromRules();
            if ($cfDestination) {
                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, $cfDestination);
                $activeDestination = $cfDestination;
            }
        }

        $pendingDestination = SiteSetting::emailRoutingPendingDestination();
        $destinationStatus = SiteSetting::emailRoutingDestinationStatus();
        $lastSyncedAt = SiteSetting::emailRoutingLastSyncedAt();

        $cfRouting = $cfConfigured ? $cloudflare->getRoutingStatus() : [
            'success' => false,
            'enabled' => false,
            'status' => 'not_configured',
        ];

        // Format aliases status table
        $aliasesTable = [];
        foreach (self::MANAGED_ALIASES as $alias) {
            $aliasesTable[] = [
                'address' => $alias,
                'destination' => $activeDestination ?: 'Not configured locally',
                'status' => $activeDestination ? 'Active' : 'Unconfigured',
                'cloudflare_status' => $cfConfigured
                    ? ($cfRouting['enabled'] ? 'Forwarding' : 'Zone Inactive')
                    : 'Configured locally',
            ];
        }

        // Outbound application email status (informational only)
        $outboundMailer = (string) config('mail.default', 'log');
        $outboundStatus = match (strtolower($outboundMailer)) {
            'log' => 'Log only',
            'smtp', 'resend', 'postmark', 'ses', 'mailgun' => 'Provider configured ('.strtoupper($outboundMailer).')',
            default => filled($outboundMailer) ? 'Provider configured ('.strtoupper($outboundMailer).')' : 'Not configured',
        };

        $recentAudits = EmailRoutingAudit::with('actor')->latest()->take(10)->get();

        return view('admin.settings.email', [
            'domain' => $cloudflare->domain(),
            'activeDestination' => $activeDestination,
            'pendingDestination' => $pendingDestination,
            'destinationStatus' => $destinationStatus,
            'lastSyncedAt' => $lastSyncedAt,
            'cfConfigured' => $cfConfigured,
            'cfRouting' => $cfRouting,
            'aliasesTable' => $aliasesTable,
            'outboundMailer' => $outboundMailer,
            'outboundStatus' => $outboundStatus,
            'recentAudits' => $recentAudits,
        ]);
    }

    public function updateDestination(Request $request, CloudflareEmailRoutingService $cloudflare): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'destination_email' => ['required', 'email', 'max:255'],
        ]);

        $proposed = strtolower(trim($validated['destination_email']));
        $active = SiteSetting::emailRoutingActiveDestination();

        if ($active && $proposed === strtolower($active)) {
            return back()->with('error', "{$proposed} is already the current active destination.");
        }

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, $proposed);

        if ($cloudflare->isConfigured()) {
            try {
                $cfAddress = $cloudflare->createDestinationAddress($proposed);
                $isAlreadyVerified = ! empty($cfAddress['verified']);

                if ($isAlreadyVerified) {
                    SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');
                    EmailRoutingAudit::record(
                        action: EmailRoutingAudit::ACTION_DESTINATION_PROPOSED,
                        previousDestination: $active,
                        proposedDestination: $proposed,
                        actorId: $request->user()->id,
                        status: 'verified',
                        details: ['provider' => 'cloudflare', 'note' => 'Destination address is already verified in Cloudflare']
                    );

                    return back()->with('status', "Proposed destination recorded. {$proposed} is already verified in Cloudflare. You may now sync the routing rules.");
                }

                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'pending');
                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_DESTINATION_PROPOSED,
                    previousDestination: $active,
                    proposedDestination: $proposed,
                    actorId: $request->user()->id,
                    status: 'pending',
                    details: ['provider' => 'cloudflare']
                );
                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_VERIFICATION_REQUESTED,
                    previousDestination: $active,
                    proposedDestination: $proposed,
                    actorId: $request->user()->id,
                    status: 'pending',
                    details: ['provider' => 'cloudflare']
                );

                return back()->with('status', "Proposed destination recorded. Cloudflare sent a verification email to {$proposed}. Once verified, sync the routing rules.");
            } catch (\Throwable $e) {
                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'failed');
                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_DESTINATION_PROPOSED,
                    previousDestination: $active,
                    proposedDestination: $proposed,
                    actorId: $request->user()->id,
                    status: 'failed',
                    details: ['error' => $e->getMessage()]
                );

                return back()->with('error', "Cloudflare destination request failed: {$e->getMessage()}");
            }
        }

        // Manual mode when Cloudflare API is not configured
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'pending');
        EmailRoutingAudit::record(
            action: EmailRoutingAudit::ACTION_DESTINATION_PROPOSED,
            previousDestination: $active,
            proposedDestination: $proposed,
            actorId: $request->user()->id,
            status: 'pending',
            details: ['mode' => 'manual']
        );

        return back()->with('status', "Proposed destination recorded locally. Cloudflare connection is not configured. Update the destination in Cloudflare Email Routing, then confirm verification here.");
    }

    public function checkVerification(Request $request, CloudflareEmailRoutingService $cloudflare): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $pending = SiteSetting::emailRoutingPendingDestination();
        $active = SiteSetting::emailRoutingActiveDestination();

        if (! $pending) {
            return back()->with('error', 'No pending destination to verify.');
        }

        if ($request->boolean('manual_confirm') && ! $cloudflare->isConfigured()) {
            SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');
            EmailRoutingAudit::record(
                action: EmailRoutingAudit::ACTION_VERIFICATION_COMPLETED,
                previousDestination: $active,
                proposedDestination: $pending,
                actorId: $request->user()->id,
                status: 'verified',
                details: ['mode' => 'manual']
            );

            return back()->with('status', "Manual verification confirmed for {$pending}. You may now sync the routing rules.");
        }

        if ($cloudflare->isConfigured()) {
            $status = $cloudflare->checkDestinationVerification($pending);
            SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, $status);

            if ($status === 'verified') {
                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_VERIFICATION_COMPLETED,
                    previousDestination: $active,
                    proposedDestination: $pending,
                    actorId: $request->user()->id,
                    status: 'verified',
                    details: ['provider' => 'cloudflare']
                );

                return back()->with('status', "Destination {$pending} is verified in Cloudflare! You may now sync the routing rules.");
            } elseif ($status === 'pending') {
                return back()->with('status', "Destination {$pending} is still pending verification in Cloudflare. Check the inbox and click the verification link.");
            } else {
                return back()->with('error', "Destination {$pending} was not found or failed verification in Cloudflare.");
            }
        }

        return back()->with('status', "Cloudflare connection not configured. Update the destination in Cloudflare Email Routing, then confirm here.");
    }

    public function syncRouting(Request $request, CloudflareEmailRoutingService $cloudflare): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $active = SiteSetting::emailRoutingActiveDestination();
        $pending = SiteSetting::emailRoutingPendingDestination();
        $status = SiteSetting::emailRoutingDestinationStatus();

        if (! $pending || $status !== 'verified') {
            return back()->with('error', 'Cannot sync routing: destination is not verified or pending destination does not exist.');
        }

        if ($cloudflare->isConfigured()) {
            try {
                $cloudflare->updateAliasRules($pending, self::MANAGED_ALIASES, $active);

                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, $pending);
                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, null);
                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');
                SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_LAST_SYNCED_AT, now()->toIso8601String());

                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_ROUTING_SWITCHED,
                    previousDestination: $active,
                    proposedDestination: $pending,
                    actorId: $request->user()->id,
                    status: 'success',
                    details: [
                        'provider' => 'cloudflare',
                        'aliases' => self::MANAGED_ALIASES,
                    ]
                );

                return back()->with('status', "Successfully switched Cloudflare Email Routing for support, hello, and billing to {$pending}.");
            } catch (\Throwable $e) {
                // Failure safety: preserve existing active destination locally
                EmailRoutingAudit::record(
                    action: EmailRoutingAudit::ACTION_SYNC_FAILED,
                    previousDestination: $active,
                    proposedDestination: $pending,
                    actorId: $request->user()->id,
                    status: 'failed',
                    details: ['error' => $e->getMessage()]
                );

                return back()->with('error', "Cloudflare routing sync failed: {$e->getMessage()}. Previous destination ({$active}) remains active.");
            }
        }

        // Manual mode sync
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, $pending);
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, null);
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_LAST_SYNCED_AT, now()->toIso8601String());

        EmailRoutingAudit::record(
            action: EmailRoutingAudit::ACTION_ROUTING_SWITCHED,
            previousDestination: $active,
            proposedDestination: $pending,
            actorId: $request->user()->id,
            status: 'success',
            details: ['mode' => 'manual']
        );

        return back()->with('status', "Active forwarding destination updated locally to {$pending}. Remember to update the routing rules in your Cloudflare dashboard.");
    }

    public function cancelPending(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $active = SiteSetting::emailRoutingActiveDestination();
        $pending = SiteSetting::emailRoutingPendingDestination();

        if ($pending) {
            SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, null);
            SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');

            EmailRoutingAudit::record(
                action: EmailRoutingAudit::ACTION_PENDING_CANCELLED,
                previousDestination: $active,
                proposedDestination: $pending,
                actorId: $request->user()->id,
                status: 'success'
            );
        }

        return back()->with('status', 'Pending destination change cancelled. Current active destination remains active.');
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isAdmin()) {
            return $next($request);
        }

        $organization = $this->resolveOrganization($request);
        if ($organization === null) {
            return $next($request);
        }

        if ($organization->allowsBroadcast()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'An active subscription is required to go on air.',
            ], 402);
        }

        return redirect()
            ->route('billing.plans')
            ->with('error', __('Subscribe or start a trial to go on air or create streams.'));
    }

    protected function resolveOrganization(Request $request): ?Organization
    {
        $stream = $request->route('stream');
        if ($stream instanceof Stream) {
            $stream->loadMissing('organization.subscription');

            return $stream->organization;
        }

        $event = $request->route('event');
        if ($event instanceof Event) {
            $event->loadMissing('organization.subscription');

            return $event->organization;
        }

        $organizationId = $request->input('organization_id');
        if ($organizationId) {
            return Organization::query()->with('subscription')->find($organizationId);
        }

        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return $user->organizations()->with('subscription')->first();
    }
}

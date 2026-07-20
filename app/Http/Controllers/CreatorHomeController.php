<?php

namespace App\Http\Controllers;

use App\Enums\OrgRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CreatorHomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->isAdmin()) {
            return redirect()->route('admin.streams.index');
        }

        $organization = $user->organizations()
            ->with(['subscription.plan', 'streams'])
            ->orderByPivot('role')
            ->first();

        if ($organization === null) {
            return redirect()->route('onboarding.show');
        }

        $stream = $organization->defaultStream();
        $studioUrl = $stream
            ? route('admin.streams.studio', $stream)
            : null;
        $signedStudioUrl = $stream
            ? URL::temporarySignedRoute('studio.stream', now()->addHours(24), ['stream' => $stream])
            : null;

        $isOwner = $user->orgRole($organization) === OrgRole::Owner;

        return view('creator.home', [
            'organization' => $organization,
            'stream' => $stream,
            'studioUrl' => $studioUrl,
            'signedStudioUrl' => $signedStudioUrl,
            'subscription' => $organization->subscription,
            'canBroadcast' => $organization->allowsBroadcast(),
            'isOwner' => $isOwner,
        ]);
    }
}

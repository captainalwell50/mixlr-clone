<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelController extends Controller
{
    public function show(Request $request, Organization $organization): View
    {
        abort_unless($organization->is_public || $request->user()?->canManageOrganization($organization), 404);

        $live = $organization->events()
            ->where('status', EventStatus::Live)
            ->latest('started_at')
            ->first();

        $liveStream = $live
            ? null
            : $organization->streams()
                ->where('status', StreamStatus::Live)
                ->where('is_public', true)
                ->latest('started_at')
                ->first();

        $upcoming = $organization->events()
            ->where('status', EventStatus::Scheduled)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '>=', now()->subHour());
            })
            ->orderBy('scheduled_at')
            ->limit(12)
            ->get();

        $recordings = Recording::query()
            ->whereHas('stream', fn ($q) => $q->where('organization_id', $organization->id))
            ->with('stream')
            ->latest('completed_at')
            ->limit(20)
            ->get();

        $following = $request->user()?->followsChannel($organization) ?? false;

        return view('channel.show', compact('organization', 'live', 'liveStream', 'upcoming', 'recordings', 'following'));
    }
}

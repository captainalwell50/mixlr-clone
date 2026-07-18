<?php

namespace App\Http\Controllers;

use App\Models\ChannelFollow;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelFollowController extends Controller
{
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        ChannelFollow::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'organization_id' => $organization->id,
        ]);

        return back()->with('status', __('You are following :name.', ['name' => $organization->name]));
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        ChannelFollow::query()
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $organization->id)
            ->delete();

        return back()->with('status', __('Unfollowed :name.', ['name' => $organization->name]));
    }
}

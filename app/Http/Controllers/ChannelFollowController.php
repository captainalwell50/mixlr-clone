<?php

namespace App\Http\Controllers;

use App\Models\ChannelFollow;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelFollowController extends Controller
{
    public function store(Request $request, Organization $organization): JsonResponse|RedirectResponse
    {
        ChannelFollow::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'organization_id' => $organization->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'following' => true,
                'message' => __('You are following :name. We’ll email you when they go on air.', [
                    'name' => $organization->name,
                ]),
            ]);
        }

        return back()->with('status', __('You are following :name. We’ll email you when they go on air.', [
            'name' => $organization->name,
        ]));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse|RedirectResponse
    {
        ChannelFollow::query()
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $organization->id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'following' => false,
                'message' => __('Unfollowed :name.', ['name' => $organization->name]),
            ]);
        }

        return back()->with('status', __('Unfollowed :name.', ['name' => $organization->name]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListenController extends Controller
{
    public function status(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        return response()->json([
            'status' => $stream->status->value,
        ]);
    }

    public function show(Request $request, Stream $stream): View|RedirectResponse
    {
        $stream->loadMissing('organization');

        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->latest('id')
            ->first();

        if ($event) {
            return redirect()->route('events.show', $event);
        }

        $this->authorizeListen($request, $stream);

        $organization = $stream->organization;
        $likeCount = $stream->likes()->count();
        $userLiked = $request->user()
            ? $stream->likes()->where('user_id', $request->user()->id)->exists()
            : false;
        $listenerCount = $stream->activeListenerCount();
        $isFollowing = $request->user()?->followsChannel($organization) ?? false;
        $galleryImages = $stream->galleryImages()->limit(24)->get();

        return view('listen', [
            'stream' => $stream,
            'organization' => $organization,
            'hlsUrl' => $stream->hlsPlaylistUrl(),
            'whepUrl' => $stream->whepUrl(),
            'likeCount' => $likeCount,
            'userLiked' => $userLiked,
            'listenerCount' => $listenerCount,
            'isFollowing' => $isFollowing,
            'galleryImages' => $galleryImages,
        ]);
    }

    public function embed(Request $request, Stream $stream): View|RedirectResponse
    {
        $stream->loadMissing('organization');

        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->latest('id')
            ->first();

        if ($event) {
            return redirect()->route('events.embed', $event);
        }

        $this->authorizeListen($request, $stream);

        return view('embed-listen', [
            'stream' => $stream,
            'hlsUrl' => $stream->hlsPlaylistUrl(),
            'whepUrl' => $stream->whepUrl(),
        ]);
    }

    private function authorizeListen(Request $request, Stream $stream): void
    {
        $organization = $stream->organization;

        abort_unless(
            ($stream->is_public && ($organization?->is_public ?? false))
            || $request->user()?->canManageOrganization($organization),
            404
        );
    }
}

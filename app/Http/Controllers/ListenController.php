<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListenController extends Controller
{
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

        return view('listen', [
            'stream' => $stream,
            'organization' => $stream->organization,
            'hlsUrl' => $stream->hlsPlaylistUrl(),
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

<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Stream;

class StudioController extends Controller
{
    public function show(Stream $stream)
    {
        $stream->loadMissing('organization');

        $liveEvent = $stream->events()
            ->where('status', EventStatus::Live)
            ->latest('started_at')
            ->first();

        $listenUrl = $liveEvent
            ? route('events.show', $liveEvent)
            : route('listen.stream', $stream);

        return view('studio', [
            'stream' => $stream,
            'whipUrl' => $stream->whipUrl(),
            'organization' => $stream->organization,
            'listenUrl' => $listenUrl,
        ]);
    }
}

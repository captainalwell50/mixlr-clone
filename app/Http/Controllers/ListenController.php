<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;

class ListenController extends Controller
{
    public function show(Stream $stream): RedirectResponse
    {
        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->latest('id')
            ->first();

        if ($event) {
            return redirect()->route('events.show', $event);
        }

        // Legacy stream without event — send to org channel if possible
        return redirect()->route('channels.show', $stream->organization);
    }

    public function embed(Stream $stream): RedirectResponse
    {
        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->latest('id')
            ->first();

        if ($event) {
            return redirect()->route('events.embed', $event);
        }

        abort(404);
    }
}

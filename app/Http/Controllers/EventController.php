<?php

namespace App\Http\Controllers;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function show(Request $request, Event $event): View|RedirectResponse
    {
        $event->load(['organization', 'stream']);

        if ($event->access === EventAccess::Private) {
            $unlocked = $request->session()->get($this->unlockKey($event));
            if (! $unlocked) {
                return view('events.password', compact('event'));
            }
        }

        $heartCount = $event->hearts()->count();
        $userHearted = $request->user()
            ? $event->hearts()->where('user_id', $request->user()->id)->exists()
            : false;
        $listenerCount = $event->show_listener_count ? $event->activeListenerCount() : null;
        $hlsUrl = $event->stream?->hlsPlaylistUrl();

        return view('events.show', compact(
            'event',
            'heartCount',
            'userHearted',
            'listenerCount',
            'hlsUrl',
        ));
    }

    public function unlock(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! $event->checkAccessPassword($validated['password'])) {
            return back()->withErrors(['password' => __('Incorrect password.')]);
        }

        $request->session()->put($this->unlockKey($event), true);

        return redirect()->route('events.show', $event);
    }

    public function embed(Request $request, Event $event): View
    {
        $event->load(['organization', 'stream']);

        if ($event->access === EventAccess::Private) {
            abort(403, 'Private events cannot be embedded.');
        }

        return view('events.embed', [
            'event' => $event,
            'hlsUrl' => $event->stream?->hlsPlaylistUrl(),
            'isLive' => $event->status === EventStatus::Live,
        ]);
    }

    private function unlockKey(Event $event): string
    {
        return 'event_unlock_'.$event->id;
    }
}

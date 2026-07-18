<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EventBroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private EventBroadcastService $broadcast) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Event::query()->with(['organization', 'stream'])->latest('scheduled_at');

        if (! $user->isAdmin()) {
            $orgIds = $user->manageableOrganizations()->pluck('organizations.id');
            $query->whereIn('organization_id', $orgIds);
        }

        $events = $query->paginate(20);

        return view('admin.events.index', compact('events'));
    }

    public function create(Request $request): View
    {
        $organizations = $request->user()->manageableOrganizations()->get();

        return view('admin.events.create', compact('organizations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $org = Organization::query()->findOrFail($validated['organization_id']);
        abort_unless($request->user()->canManageOrganization($org), 403);

        $event = Event::query()->create([
            ...$validated,
            'status' => EventStatus::Scheduled,
            'access_password' => $validated['access'] === EventAccess::Private->value
                ? ($validated['access_password'] ?? null)
                : null,
        ]);

        $this->broadcast->ensureStream($event);

        return redirect()->route('admin.events.edit', $event)
            ->with('status', __('Event scheduled.'));
    }

    public function edit(Request $request, Event $event): View
    {
        abort_unless($request->user()->canManageEvent($event), 403);

        $event->load(['organization', 'stream']);
        $organizations = $request->user()->manageableOrganizations()->get();
        $stream = $this->broadcast->ensureStream($event);
        $event->refresh();

        $studioUrl = URL::temporarySignedRoute(
            'studio.stream',
            now()->addHours(24),
            ['stream' => $stream]
        );

        return view('admin.events.edit', compact('event', 'organizations', 'studioUrl', 'stream'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->canManageEvent($event), 403);

        $validated = $this->validated($request);
        $org = Organization::query()->findOrFail($validated['organization_id']);
        abort_unless($request->user()->canManageOrganization($org), 403);

        $password = $event->access_password;
        if ($validated['access'] === EventAccess::Private->value) {
            if (! empty($validated['access_password'])) {
                $password = $validated['access_password'];
            }
        } else {
            $password = null;
        }

        unset($validated['access_password']);
        $event->update([
            ...$validated,
            'access_password' => $password,
            'chat_enabled' => $request->boolean('chat_enabled'),
            'show_listener_count' => $request->boolean('show_listener_count'),
        ]);

        return redirect()->route('admin.events.edit', $event)
            ->with('status', __('Event updated.'));
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->canManageEvent($event), 403);
        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('status', __('Event deleted.'));
    }

    public function goLive(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->canManageEvent($event), 403);
        $this->broadcast->ensureStream($event);
        $event->refresh();
        $this->broadcast->markLive($event);

        return redirect()->route('admin.streams.studio', $event->stream)
            ->with('status', __('Event is live — studio opened.'));
    }

    public function end(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->canManageEvent($event), 403);
        $this->broadcast->markEnded($event);

        return back()->with('status', __('Event ended.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
            'access' => ['required', Rule::in(['public', 'unlisted', 'private'])],
            'access_password' => ['nullable', 'string', 'max:255'],
            'chat_enabled' => ['sometimes', 'boolean'],
            'show_listener_count' => ['sometimes', 'boolean'],
        ]);

        $data['chat_enabled'] = $request->boolean('chat_enabled', true);
        $data['show_listener_count'] = $request->boolean('show_listener_count', true);

        return $data;
    }
}

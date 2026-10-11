<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StreamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Stream::query()->with('organization');

        $manageableOrgs = ! $user->isAdmin()
            ? $user->manageableOrganizations()->orderBy('name')->get(['organizations.id', 'organizations.name'])
            : null;
        $orgIds = $manageableOrgs?->pluck('id');

        if (! $user->isAdmin()) {
            $query->whereIn('organization_id', $orgIds);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('uuid', 'like', "%{$q}%")
                    ->orWhereHas('organization', function ($org) use ($q) {
                        $org->where('name', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%");
                    });
            });
        }

        $status = $request->query('status');
        if (in_array($status, ['live', 'offline'], true)) {
            $query->where('status', $status);
        }

        $organizationId = $request->query('organization_id');
        if (filled($organizationId)) {
            $query->where('organization_id', $organizationId);
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'oldest' => $query->oldest('id'),
            'title' => $query->orderBy('title'),
            'status' => $query->orderByDesc('status')->latest('id'),
            default => $query->latest('id'),
        };

        $streams = $query->paginate(20)->withQueryString();

        // Operational summary counts
        $summaryBase = Stream::query();
        if (! $user->isAdmin()) {
            $summaryBase->whereIn('organization_id', $orgIds);
        }

        $liveCount = (clone $summaryBase)->where('status', StreamStatus::Live)->count();
        $totalCount = (clone $summaryBase)->count();
        $offlineCount = (clone $summaryBase)->where('status', StreamStatus::Offline)->count();

        $broadcastsToday = Event::query()
            ->when(! $user->isAdmin(), fn ($eq) => $eq->whereIn('organization_id', $orgIds))
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        $organizations = $user->isAdmin()
            ? Organization::query()->orderBy('name')->get(['id', 'name'])
            : $manageableOrgs;

        return view('admin.streams.index', compact(
            'streams',
            'q',
            'status',
            'organizationId',
            'sort',
            'liveCount',
            'totalCount',
            'offlineCount',
            'broadcastsToday',
            'organizations',
        ));
    }

    public function create(Request $request): View
    {
        $organizations = $request->user()->manageableOrganizations()->get();

        return view('admin.streams.create', compact('organizations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['sometimes', 'boolean'],
            'chat_enabled' => ['sometimes', 'boolean'],
        ]);

        $org = Organization::query()->findOrFail($validated['organization_id']);
        if (! $request->user()->canManageOrganization($org)) {
            abort(403);
        }

        $maxStreams = (int) $org->planLimit('max_streams', 1);
        if ($maxStreams > 0 && $org->streams()->count() >= $maxStreams) {
            return back()
                ->withInput()
                ->with('error', __('This plan allows :max stream(s). Upgrade to add more.', ['max' => $maxStreams]));
        }

        Stream::query()->create([
            'organization_id' => $validated['organization_id'],
            'uuid' => (string) Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_public' => $request->boolean('is_public', true),
            'chat_enabled' => $request->boolean('chat_enabled', true),
            'status' => StreamStatus::Offline,
        ]);

        return redirect()->route('admin.streams.index')
            ->with('status', __('Stream created.'));
    }

    public function edit(Request $request, Stream $stream): View
    {
        $this->authorizeStream($request, $stream);

        $stream->load([
            'recordings' => fn ($query) => $query->latest('completed_at')->limit(40),
        ]);
        $organizations = $request->user()->manageableOrganizations()->get();
        $studioUrl = URL::temporarySignedRoute(
            'studio.stream',
            now()->addHours(24),
            ['stream' => $stream]
        );

        return view('admin.streams.edit', compact('stream', 'organizations', 'studioUrl'));
    }

    public function update(Request $request, Stream $stream): RedirectResponse
    {
        $this->authorizeStream($request, $stream);

        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:offline,live'],
            'is_public' => ['sometimes', 'boolean'],
            'chat_enabled' => ['sometimes', 'boolean'],
        ]);

        $org = Organization::query()->findOrFail($validated['organization_id']);
        if (! $request->user()->canManageOrganization($org)) {
            abort(403);
        }

        $stream->update([
            'organization_id' => $validated['organization_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => StreamStatus::from($validated['status']),
            'is_public' => $request->boolean('is_public'),
            'chat_enabled' => $request->boolean('chat_enabled'),
        ]);

        return redirect()->route('admin.streams.edit', $stream)
            ->with('status', __('Stream updated.'));
    }

    public function destroy(Request $request, Stream $stream): RedirectResponse
    {
        $this->authorizeStream($request, $stream);
        $stream->delete();

        return redirect()->route('admin.streams.index')
            ->with('status', __('Stream deleted.'));
    }

    public function regenerateKey(Request $request, Stream $stream): RedirectResponse
    {
        $this->authorizeStream($request, $stream);
        $stream->regenerateStreamKey();

        return redirect()->route('admin.streams.edit', $stream)
            ->with('status', __('Stream key regenerated. Update OBS and signed studio links.'));
    }

    private function authorizeStream(Request $request, Stream $stream): void
    {
        if (! $request->user()->canManageStream($stream)) {
            abort(403);
        }
    }
}

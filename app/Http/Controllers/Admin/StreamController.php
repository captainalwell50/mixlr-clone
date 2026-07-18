<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
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
        $query = Stream::query()->with('organization')->latest();

        if (! $user->isAdmin()) {
            $orgIds = $user->manageableOrganizations()->pluck('organizations.id');
            $query->whereIn('organization_id', $orgIds);
        }

        $streams = $query->paginate(20);

        return view('admin.streams.index', compact('streams'));
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

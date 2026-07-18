<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $organizations = $user->isAdmin()
            ? Organization::query()->orderBy('name')->paginate(20)
            : $user->organizations()->orderBy('name')->paginate(20);

        return view('admin.organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return view('admin.organizations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'support_url' => ['nullable', 'url', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = Organization::query()->where('slug', $slug)->exists()
            ? $slug.'-'.Str::lower(Str::random(4))
            : $slug;

        $organization = Organization::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'tagline' => $validated['tagline'] ?? null,
            'theme_color' => $validated['theme_color'] ?? '#3d9b7a',
            'support_url' => $validated['support_url'] ?? null,
            'is_public' => $request->boolean('is_public', true),
        ]);

        $organization->users()->attach($request->user()->id, [
            'role' => OrgRole::Owner->value,
        ]);

        return redirect()->route('admin.organizations.edit', $organization)
            ->with('status', __('Channel created.'));
    }

    public function edit(Request $request, Organization $organization): View
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'.$organization->id],
            'tagline' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'support_url' => ['nullable', 'url', 'max:500'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'artwork_path' => ['nullable', 'string', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $organization->update([
            ...$validated,
            'is_public' => $request->boolean('is_public'),
            'branding_config' => array_merge($organization->branding_config ?? [], [
                'accent' => $validated['theme_color'] ?? $organization->theme_color,
            ]),
        ]);

        return redirect()->route('admin.organizations.edit', $organization)
            ->with('status', __('Channel updated.'));
    }
}

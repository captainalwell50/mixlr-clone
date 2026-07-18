<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationMemberController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorizeManage($organization);

        $members = $organization->users()->orderBy('name')->get();

        return view('admin.organizations.members', compact('organization', 'members'));
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($organization);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in([OrgRole::Owner->value, OrgRole::Admin->value, OrgRole::Member->value])],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null) {
            $user = User::query()->create([
                'name' => $validated['name'] ?: Str::before($validated['email'], '@'),
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(32)),
                'is_admin' => false,
            ]);
        }

        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $validated['role']],
        ]);

        return back()->with('status', __('Member added.'));
    }

    public function update(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorizeManage($organization);

        $validated = $request->validate([
            'role' => ['required', Rule::in([OrgRole::Owner->value, OrgRole::Admin->value, OrgRole::Member->value])],
        ]);

        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        $organization->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('status', __('Member updated.'));
    }

    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $this->authorizeManage($organization);

        $organization->users()->detach($user->id);

        return back()->with('status', __('Member removed.'));
    }

    private function authorizeManage(Organization $organization): void
    {
        $actor = request()->user();
        if ($actor === null || ! $actor->canManageOrganization($organization)) {
            abort(403);
        }
    }
}

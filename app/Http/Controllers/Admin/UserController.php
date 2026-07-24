<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->withCount('organizations')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.users.form', [
            'user' => new User(['is_admin' => false]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $request->boolean('is_admin'),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.edit', $user)
            ->with('status', __('User created.'));
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $user->load('organizations');

        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $makeAdmin = $request->boolean('is_admin');

        if ($user->is_admin && ! $makeAdmin && $this->isLastAdmin($user)) {
            return back()->withInput()->with('error', __('Cannot remove admin from the last platform admin.'));
        }

        if ($request->user()->is($user) && $user->is_admin && ! $makeAdmin) {
            return back()->withInput()->with('error', __('You cannot remove your own admin access.'));
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $makeAdmin,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('admin.users.edit', $user)
            ->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($request->user()->is($user)) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        if ($user->is_admin && $this->isLastAdmin($user)) {
            return back()->with('error', __('Cannot delete the last platform admin.'));
        }

        $user->organizations()->detach();
        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', __('User deleted.'));
    }

    private function isLastAdmin(User $user): bool
    {
        return User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}

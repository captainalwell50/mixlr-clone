<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\OutboundEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'supportEmail' => config('app.support_email'),
        ]);
    }

    public function update(Request $request, OutboundEmailService $outbound): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 403);

        $hasPassword = $user->hasPassword();

        // Support 'profile_name' or 'display_name' if sent by client
        if (! $request->filled('name') && ($request->filled('profile_name') || $request->filled('display_name'))) {
            $request->merge([
                'name' => $request->input('profile_name') ?? $request->input('display_name'),
            ]);
        }

        $isPasswordChange = $request->filled('password') || $request->filled('current_password');

        if ($isPasswordChange) {
            $rules = [
                'current_password' => $hasPassword ? ['required', 'current_password:web'] : ['nullable'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ];

            if ($request->has('name')) {
                $rules['name'] = ['required', 'string', 'max:255'];
            }

            if ($request->has('email')) {
                $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
            }

            if ($request->hasFile('avatar')) {
                $rules['avatar'] = ['nullable', 'image', 'max:5120'];
            }

            $validated = $request->validate($rules);

            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email']) && strtolower(trim($validated['email'])) !== strtolower($user->email)) {
                $user->email = strtolower(trim($validated['email']));
                $user->email_verified_at = null;
            }

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
                $previous = $user->avatar_path;
                $user->avatar_path = $path;
                if ($previous && ! str_starts_with($previous, 'http')) {
                    Storage::disk('public')->delete($previous);
                }
            }

            $user->password = Hash::make($validated['password']);
            $user->setRememberToken(Str::random(60));
            $user->save();

            // Keep user safely logged in with refreshed session
            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->put('password_hash_web', $user->getAuthPassword());
                $request->session()->regenerate();
            }

            // Outbound notification safely isolated so mail failure does not disrupt persistence
            try {
                $eventKey = 'password-changed:'.$user->id.':'.now()->format('YmdHis');
                $outbound->sendTransactional(
                    mailable: new PasswordChangedMail($user, now(), $request->ip()),
                    recipient: $user->email,
                    type: 'password_changed',
                    eventKey: $eventKey,
                    user: $user,
                    metadata: [
                        'ip' => $request->ip(),
                        'source' => 'account_settings',
                    ]
                );
            } catch (Throwable $e) {
                Log::error('PasswordChangedMail dispatch failed: '.$e->getMessage(), [
                    'user_id' => $user->id,
                ]);
            }

            return redirect()->route('account.edit')
                ->with('status', __('Account updated. Your new password is active.'));
        }

        // Profile update only
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($request->has('email')) {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        if ($request->hasFile('avatar')) {
            $rules['avatar'] = ['nullable', 'image', 'max:5120'];
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];

        if (isset($validated['email']) && strtolower(trim($validated['email'])) !== strtolower($user->email)) {
            $user->email = strtolower(trim($validated['email']));
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
            $previous = $user->avatar_path;
            $user->avatar_path = $path;
            if ($previous && ! str_starts_with($previous, 'http')) {
                Storage::disk('public')->delete($previous);
            }
        }

        $user->save();

        return redirect()->route('account.edit')
            ->with('status', __('Profile updated successfully.'));
    }

    public function destroy(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:DELETE'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $deletion->assertCanDelete($user);
        } catch (RuntimeException $e) {
            return redirect()->route('account.edit')
                ->with('error', __($e->getMessage()));
        }

        $userId = $user->id;

        // End the session before deleting so auth/session persistence cannot
        // re-save the in-memory user model after the row is removed.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $fresh = User::query()->find($userId);
        if ($fresh !== null) {
            $deletion->delete($fresh);
        }

        return redirect()->route('login')
            ->with('status', __('Your account has been deleted.'));
    }

    public function disconnectIdentity(Request $request, string $provider): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 403);

        $identity = $user->identities()->where('provider', $provider)->first();
        if ($identity === null) {
            return redirect()->route('account.edit')->with('error', __('Connected account not found.'));
        }

        if (! $user->hasPassword()) {
            return redirect()->route('account.edit')
                ->with('error', __('You cannot disconnect your Google account without first setting a password.'));
        }

        $identity->delete();

        return redirect()->route('account.edit')
            ->with('status', __('Google account disconnected successfully.'));
    }
}

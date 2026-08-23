<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'supportEmail' => config('app.support_email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'password' => $validated['password'],
        ])->save();

        return redirect()->route('account.edit')
            ->with('status', __('Account updated. Your new password is active.'));
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
}

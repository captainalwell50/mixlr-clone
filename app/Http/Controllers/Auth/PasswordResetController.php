<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordSetupMail;
use App\Models\User;
use App\Services\OutboundEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, OutboundEmailService $outbound): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $request->input('email'))->first();

        if ($user) {
            $token = Password::createToken($user);
            $actionUrl = route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);

            $eventKey = 'password-reset:'.$user->id.':'.now()->format('YmdH');

            $mailable = new PasswordSetupMail(
                user: $user,
                actionUrl: $actionUrl,
                isInitialSetup: false
            );

            $outbound->sendTransactional(
                mailable: $mailable,
                recipient: $user->email,
                type: 'password_reset',
                eventKey: $eventKey,
                user: $user
            );
        }

        return back()->with('status', __('If an account exists for that email, a password reset link has been dispatched.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'request' => $request,
            'token' => $token,
        ]);
    }

    public function update(Request $request, OutboundEmailService $outbound): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $resetUser = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        if ($resetUser instanceof User) {
            $eventKey = 'password-changed:'.$resetUser->id.':'.now()->format('YmdHis');
            $outbound->sendTransactional(
                mailable: new PasswordChangedMail($resetUser, now(), $request->ip()),
                recipient: $resetUser->email,
                type: 'password_changed',
                eventKey: $eventKey,
                user: $resetUser,
                metadata: [
                    'ip' => $request->ip(),
                    'source' => 'password_reset_flow',
                ]
            );
        }

        return redirect()->route('login')->with('status', __($status));
    }
}

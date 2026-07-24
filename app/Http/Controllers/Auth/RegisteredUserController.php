<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\RealPersonName;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        if (! config('app.registration_enabled')) {
            abort(404);
        }

        $request->session()->put('registration_started_at', time());

        return view('auth.register', [
            'turnstileSiteKey' => TurnstileVerifier::enabled()
                ? config('registration.turnstile.site_key')
                : null,
        ]);
    }

    public function store(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        if (! config('app.registration_enabled')) {
            abort(404);
        }

        $honeypot = (string) config('registration.honeypot', 'website');

        if (filled($request->input($honeypot))) {
            throw ValidationException::withMessages([
                'email' => __('Unable to complete registration. Please try again.'),
            ]);
        }

        $this->assertMinimumFormTime($request);

        if (TurnstileVerifier::enabled() && ! $turnstile->verify(
            $request->input('cf-turnstile-response'),
            $request->ip()
        )) {
            throw ValidationException::withMessages([
                'email' => __('Please complete the security check and try again.'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', new RealPersonName],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class, new NotDisposableEmail],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // User model casts password as hashed — pass plaintext once.
            'password' => $validated['password'],
            'is_admin' => false,
        ]);

        event(new Registered($user));

        // Soft flag only — do not lock out when mail is log/array or verification
        // routes are missing. Operator-created users set email_verified_at themselves.
        if (config('registration.require_verification')) {
            // Reserved for a future MustVerifyEmail + verification route flow.
        }

        Auth::login($user);

        $request->session()->forget('registration_started_at');

        return redirect()->route('onboarding.show');
    }

    private function assertMinimumFormTime(Request $request): void
    {
        $minSeconds = (int) config('registration.min_form_seconds', 3);

        if ($minSeconds <= 0) {
            return;
        }

        $startedAt = $request->session()->get('registration_started_at');

        if (! is_numeric($startedAt)) {
            throw ValidationException::withMessages([
                'email' => __('Please reload the registration page and try again.'),
            ]);
        }

        $elapsed = time() - (int) $startedAt;

        if ($elapsed < $minSeconds) {
            throw ValidationException::withMessages([
                'email' => __('Please wait a moment and try again.'),
            ]);
        }

        // Stale form (opened > 2 hours ago) — ask for a fresh page.
        if ($elapsed > 7200) {
            $request->session()->put('registration_started_at', time());

            throw ValidationException::withMessages([
                'email' => __('This form expired. Please try again.'),
            ]);
        }
    }
}

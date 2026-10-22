<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google OAuth.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $intent = $request->query('intent', 'listener');
        if (! in_array($intent, ['listener', 'creator'], true)) {
            $intent = 'listener';
        }
        $request->session()->put('google_auth_intent', $intent);

        if ($request->user() !== null) {
            $request->session()->put('google_auth_user_id', $request->user()->id);
        } else {
            $request->session()->forget('google_auth_user_id');
        }

        $intended = self::sanitizeIntendedUrl($request->query('intended'));
        if ($intended !== null) {
            $request->session()->put('url.intended', $intended);
            $request->session()->put('google_auth_intended', $intended);
        }

        return Socialite::driver('google')
            ->scopes(config('services.google.scopes', ['openid', 'profile', 'email']))
            ->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('Google authentication was cancelled or failed. Please try again.'),
            ]);
        }

        $googleId = (string) $googleUser->getId();
        $rawEmail = (string) $googleUser->getEmail();
        $normalizedEmail = mb_strtolower(trim($rawEmail));

        if ($googleId === '' || $normalizedEmail === '') {
            return redirect()->route('login')->withErrors([
                'email' => __('No valid email or identity was received from Google.'),
            ]);
        }

        $rawUser = (array) ($googleUser->user ?? (method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : []));
        $emailVerified = (bool) ($rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false);

        $intent = $request->session()->pull('google_auth_intent', 'listener');
        $intendedUrl = $request->session()->pull('google_auth_intended')
            ?: $request->session()->pull('url.intended');

        $connectingUserId = $request->session()->pull('google_auth_user_id');
        if ($connectingUserId !== null) {
            $connectingUser = User::query()->find($connectingUserId);
            if ($connectingUser !== null) {
                $existingIdentity = UserIdentity::query()
                    ->where('provider', 'google')
                    ->where('provider_user_id', $googleId)
                    ->first();

                if ($existingIdentity !== null && $existingIdentity->user_id !== $connectingUser->id) {
                    return redirect()->route('account.edit')->withErrors([
                        'email' => __('This Google account is already connected to another SoundMix profile.'),
                    ]);
                }

                if (! $emailVerified) {
                    return redirect()->route('account.edit')->withErrors([
                        'email' => __('Your Google email is not verified. Account linking was denied for your security.'),
                    ]);
                }

                UserIdentity::query()->updateOrCreate(
                    [
                        'user_id' => $connectingUser->id,
                        'provider' => 'google',
                    ],
                    [
                        'provider_user_id' => $googleId,
                        'provider_email' => $normalizedEmail,
                        'avatar_url' => $googleUser->getAvatar(),
                        'metadata' => [
                            'name' => $googleUser->getName(),
                            'locale' => $rawUser['locale'] ?? null,
                        ],
                    ]
                );

                if ($connectingUser->email_verified_at === null) {
                    $connectingUser->update(['email_verified_at' => now()]);
                }

                if (empty($connectingUser->avatar_path) && filled($googleUser->getAvatar())) {
                    $connectingUser->update(['avatar_path' => $googleUser->getAvatar()]);
                }

                return redirect()->route('account.edit')
                    ->with('status', __('Google account connected successfully.'));
            }
        }

        try {
            $user = DB::transaction(function () use ($googleId, $normalizedEmail, $googleUser, $rawUser, $emailVerified) {
                // 1. Look up existing identity by provider + provider_user_id
                $identity = UserIdentity::query()
                    ->where('provider', 'google')
                    ->where('provider_user_id', $googleId)
                    ->first();

                if ($identity !== null) {
                    $existingUser = $identity->user;

                    $identity->update([
                        'provider_email' => $normalizedEmail,
                        'avatar_url' => $googleUser->getAvatar() ?: $identity->avatar_url,
                        'metadata' => [
                            'name' => $googleUser->getName(),
                            'locale' => $rawUser['locale'] ?? null,
                        ],
                    ]);

                    if (empty($existingUser->avatar_path) && filled($googleUser->getAvatar())) {
                        $existingUser->update(['avatar_path' => $googleUser->getAvatar()]);
                    }

                    if ($existingUser->email_verified_at === null && $emailVerified) {
                        $existingUser->update(['email_verified_at' => now()]);
                    }

                    return $existingUser;
                }

                // 2. Check if a User exists with the normalized email
                $matchedUser = User::query()->where('email', $normalizedEmail)->first();

                if ($matchedUser !== null) {
                    if (! $emailVerified) {
                        throw new DomainException(__('Your Google email is not verified. Account linking was denied for your security.'));
                    }

                    UserIdentity::query()->create([
                        'user_id' => $matchedUser->id,
                        'provider' => 'google',
                        'provider_user_id' => $googleId,
                        'provider_email' => $normalizedEmail,
                        'avatar_url' => $googleUser->getAvatar(),
                        'metadata' => [
                            'name' => $googleUser->getName(),
                            'locale' => $rawUser['locale'] ?? null,
                        ],
                    ]);

                    if ($matchedUser->email_verified_at === null) {
                        $matchedUser->update(['email_verified_at' => now()]);
                    }

                    if (empty($matchedUser->avatar_path) && filled($googleUser->getAvatar())) {
                        $matchedUser->update(['avatar_path' => $googleUser->getAvatar()]);
                    }

                    return $matchedUser;
                }

                // 3. Create new User
                $displayName = (string) $googleUser->getName();
                if (trim($displayName) === '') {
                    $displayName = explode('@', $normalizedEmail)[0];
                }

                $newUser = User::query()->create([
                    'name' => mb_substr($displayName, 0, 80),
                    'email' => $normalizedEmail,
                    'password' => null,
                    'email_verified_at' => $emailVerified ? now() : null,
                    'avatar_path' => $googleUser->getAvatar(),
                    'is_admin' => false,
                ]);

                UserIdentity::query()->create([
                    'user_id' => $newUser->id,
                    'provider' => 'google',
                    'provider_user_id' => $googleId,
                    'provider_email' => $normalizedEmail,
                    'avatar_url' => $googleUser->getAvatar(),
                    'metadata' => [
                        'name' => $googleUser->getName(),
                        'locale' => $rawUser['locale'] ?? null,
                    ],
                ]);

                return $newUser;
            });
        } catch (DomainException $e) {
            return redirect()->route('login')->withErrors([
                'email' => $e->getMessage(),
            ]);
        }

        // Authenticate with persistent remember cookie
        Auth::login($user, true);
        $request->session()->regenerate();

        // Flash identity data for frontend presentation
        $request->session()->flash('soundmix_auth_success', [
            'name' => $user->name,
            'avatar' => $user->avatarUrl(),
            'email' => $user->email,
        ]);

        if ($intendedUrl !== null && ($safeIntended = self::sanitizeIntendedUrl($intendedUrl)) !== null) {
            return redirect()->to($safeIntended);
        }

        if ($intent === 'creator') {
            if (! $user->organizations()->exists()) {
                return redirect()->route('onboarding.show');
            }

            return redirect()->route('creator.home');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->organizations()->exists()) {
            return redirect()->route('creator.home');
        }

        return redirect()->route('discover');
    }

    /**
     * Validate that an intended URL is an internal relative path.
     */
    public static function sanitizeIntendedUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $trimmed = trim($url);

        // Reject protocol-relative URLs (//example.com)
        if (str_starts_with($trimmed, '//')) {
            return null;
        }

        // Reject absolute URI schemes (http:, https:, javascript:, data:, etc.)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $trimmed)) {
            return null;
        }

        // Reject Windows-style directory traversal / backslashes
        if (str_contains($trimmed, '\\')) {
            return null;
        }

        // Must begin with a single slash
        if (! str_starts_with($trimmed, '/')) {
            return null;
        }

        return $trimmed;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->forceFill(['name' => $validated['name']])->save();

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Password updated.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $path = $validated['avatar']->store('avatars/'.$user->id, 'public');

        $previous = $user->avatar_path;
        if (is_string($previous) && $previous !== '' && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function destroy(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        try {
            $deletion->delete($user);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Account deleted.']);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $orgs = $user->organizations()
            ->with(['subscription.plan'])
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'creator_type' => $org->creator_type?->value,
                'theme_color' => $org->themeColor(),
                'artwork_url' => $org->artworkUrl(),
                'can_broadcast' => $org->allowsBroadcast(),
                'role' => $org->pivot->role ?? null,
            ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatarUrl(),
            'is_admin' => $user->isAdmin(),
            'onboarded' => $user->isAdmin() || $user->organizations()->exists(),
            'organizations' => $orgs,
        ];
    }
}

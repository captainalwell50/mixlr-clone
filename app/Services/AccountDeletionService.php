<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AccountDeletionService
{
    public function assertCanDelete(User $user): void
    {
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            throw new RuntimeException('Cannot delete the last platform admin account.');
        }
    }

    /**
     * Permanently delete a user account (tokens, follows, org memberships).
     * Does not delete organizations/streams — admins retain Operator control of channels.
     *
     * @throws RuntimeException when the user is the last platform admin
     */
    public function delete(User $user): void
    {
        $this->assertCanDelete($user);

        $avatar = $user->avatar_path;
        if (is_string($avatar) && $avatar !== '' && ! str_starts_with($avatar, 'http')) {
            Storage::disk('public')->delete($avatar);
        }

        $user->followedChannels()->detach();
        $user->organizations()->detach();
        $user->tokens()->delete();
        $user->delete();
    }

    private function isLastAdmin(User $user): bool
    {
        return User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}

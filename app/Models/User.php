<?php

namespace App\Models;

use App\Enums\OrgRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function orgRole(Organization $organization): ?OrgRole
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        return OrgRole::tryFrom((string) $membership->pivot->role);
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->isAdmin() || $this->orgRole($organization) !== null;
    }

    public function canManageOrganization(Organization $organization): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $role = $this->orgRole($organization);

        return $role?->canManage() ?? false;
    }

    public function canManageStream(Stream $stream): bool
    {
        return $this->canManageOrganization($stream->organization);
    }

    public function manageableOrganizations()
    {
        if ($this->isAdmin()) {
            return Organization::query()->orderBy('name');
        }

        return $this->organizations()
            ->wherePivotIn('role', [OrgRole::Owner->value, OrgRole::Admin->value])
            ->orderBy('name');
    }

    public function followedChannels(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'channel_follows')
            ->withTimestamps();
    }

    public function followsChannel(Organization $organization): bool
    {
        return $this->followedChannels()->where('organizations.id', $organization->id)->exists();
    }

    public function canManageEvent(Event $event): bool
    {
        return $this->canManageOrganization($event->organization);
    }
}

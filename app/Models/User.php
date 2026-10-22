<?php

namespace App\Models;

use App\Enums\OrgRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'is_admin',
        'avatar_path',
        'marketing_email_opt_in',
        'marketing_unsubscribed_at',
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
            'marketing_email_opt_in' => 'boolean',
            'marketing_unsubscribed_at' => 'datetime',
        ];
    }

    public function allowsMarketingEmail(): bool
    {
        return (bool) $this->marketing_email_opt_in && $this->marketing_unsubscribed_at === null;
    }

    public function optInToMarketing(): void
    {
        $this->update([
            'marketing_email_opt_in' => true,
            'marketing_unsubscribed_at' => null,
        ]);
    }

    public function unsubscribeFromMarketing(): void
    {
        $this->update([
            'marketing_email_opt_in' => false,
            'marketing_unsubscribed_at' => now(),
        ]);
    }

    public function unsubscribeUrl(): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('email.unsubscribe', ['user' => $this->id]);
    }

    public function emailDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function hasGoogleIdentity(): bool
    {
        if ($this->relationLoaded('identities')) {
            return $this->identities->contains('provider', 'google');
        }

        return $this->identities()->where('provider', 'google')->exists();
    }

    public function googleIdentity(): ?UserIdentity
    {
        if ($this->relationLoaded('identities')) {
            return $this->identities->firstWhere('provider', 'google');
        }

        return $this->identities()->where('provider', 'google')->first();
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function affiliateAccount(): HasOne
    {
        return $this->hasOne(AffiliateAccount::class);
    }

    public function orgRole(Organization $organization): ?OrgRole
    {
        if ($organization->pivot && isset($organization->pivot->role)) {
            return OrgRole::tryFrom((string) $organization->pivot->role);
        }

        if ($this->relationLoaded('organizations')) {
            $membership = $this->organizations->firstWhere('id', $organization->id);
            if ($membership && $membership->pivot && isset($membership->pivot->role)) {
                return OrgRole::tryFrom((string) $membership->pivot->role);
            }
        }

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

    public function avatarUrl(): ?string
    {
        $path = $this->avatar_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}

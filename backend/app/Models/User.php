<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'agency_id', 'created_by', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_DG = 'dg';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_MANAGER = 'manager';

    public function isDg(): bool
    {
        return $this->role === self::ROLE_DG;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function canManageRole(string $role): bool
    {
        if ($this->isDg()) {
            return in_array($role, [self::ROLE_SUPERVISOR, self::ROLE_MANAGER], true);
        }

        return $this->isSupervisor() && $role === self::ROLE_MANAGER;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function supervisedAgencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_supervisor', 'supervisor_id', 'agency_id')
            ->withTimestamps();
    }

    public function supervisesAgency(int $agencyId): bool
    {
        if (! $this->isSupervisor()) {
            return false;
        }

        return $this->supervisedAgencies()
            ->whereKey($agencyId)
            ->exists();
    }

    public function managedTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'manager_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}

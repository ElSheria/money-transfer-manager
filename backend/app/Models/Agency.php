<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'province', 'city', 'commune', 'address', 'phone', 'is_active'])]
class Agency extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function managers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agency_supervisor', 'agency_id', 'supervisor_id')
            ->withTimestamps();
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'source_agency_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'destination_agency_id');
    }
}

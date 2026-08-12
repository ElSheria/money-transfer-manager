<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'sender_customer_id',
    'receiver_customer_id',
    'source_agency_id',
    'destination_agency_id',
    'manager_id',
    'withdrawn_by',
    'amount',
    'currency',
    'fee',
    'status',
    'reason',
    'sent_at',
    'withdrawn_at',
])]
class Transfer extends Model
{
    use HasFactory;

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'sent_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sender_customer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'receiver_customer_id');
    }

    public function sourceAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'source_agency_id');
    }

    public function destinationAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'destination_agency_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TransferNotification::class);
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppPayment extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'spp_billing_id',
        'branch_id',
        'received_by',
        'amount',
        'reference',
        'payment_method',
        'notes',
        'paid_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(SppBilling::class, 'spp_billing_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $fillable = [
        'name',
        'level',
        'address',
        'leader_name',
        'leader_title',
        'phone',
        'monthly_transport_allowance',
        'substitution_allowance_per_period',
    ];

    protected function casts(): array
    {
        return [
            'monthly_transport_allowance' => 'decimal:2',
            'substitution_allowance_per_period' => 'decimal:2',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}

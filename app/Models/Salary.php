<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'period',
        'incentives',
        'incentive_details',
        'position_salary',
        'position_details',
        'allowances',
        'allowance_details',
        'deductions',
        'net_salary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'incentives' => 'decimal:2',
            'incentive_details' => 'array',
            'position_salary' => 'decimal:2',
            'position_details' => 'array',
            'allowances' => 'decimal:2',
            'allowance_details' => 'array',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualIncentiveAssignment extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'academic_year_id',
        'employee_id',
        'branch_incentive_id',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function incentive(): BelongsTo
    {
        return $this->belongsTo(BranchIncentive::class, 'branch_incentive_id');
    }
}

<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchPosition extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $fillable = [
        'branch_id',
        'academic_year_id',
        'name',
        'requires_class',
        'monthly_salary_addition',
        'monthly_allowance',
        'monthly_incentive',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_class' => 'boolean',
            'monthly_salary_addition' => 'decimal:2',
            'monthly_allowance' => 'decimal:2',
            'monthly_incentive' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeePositionAssignment::class);
    }
}

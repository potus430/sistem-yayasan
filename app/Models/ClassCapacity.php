<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCapacity extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $fillable = [
        'branch_id',
        'academic_year_id',
        'class_name',
        'capacity',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}

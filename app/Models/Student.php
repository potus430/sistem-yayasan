<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $fillable = [
        'branch_id',
        'nisn',
        'name',
        'current_class',
        'admission_academic_year_id',
        'enrollment_type',
        'gender',
        'birth_place',
        'birth_date',
        'guardian_name',
        'guardian_phone',
        'address',
        'previous_school',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function admissionAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'admission_academic_year_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }
}

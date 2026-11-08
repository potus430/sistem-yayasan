<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $fillable = [
        'branch_id',
        'academic_year_id',
        'type',
        'registration_number',
        'name',
        'nisn',
        'gender',
        'birth_place',
        'birth_date',
        'guardian_name',
        'guardian_phone',
        'address',
        'previous_school',
        'applied_class',
        'status',
        'notes',
        'student_id',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

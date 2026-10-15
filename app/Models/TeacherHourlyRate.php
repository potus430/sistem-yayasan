<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class TeacherHourlyRate extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $table = 'branch_teacher_hourly_rates';

    protected $fillable = ['branch_id', 'academic_year_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}

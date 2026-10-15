<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterSlot extends Model
{
    use HasUuidV7;

    protected $fillable = ['branch_id', 'academic_year_id', 'employee_id', 'subject', 'class_name', 'room', 'weekday', 'period'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

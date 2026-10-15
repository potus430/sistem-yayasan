<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubstitution extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'roster_slot_id',
        'absent_employee_id',
        'substitute_employee_id',
        'date',
        'period',
        'is_cancelled',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'period' => 'integer', 'is_cancelled' => 'boolean'];
    }

    public function absentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'absent_employee_id');
    }

    public function substituteEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function rosterSlot(): BelongsTo
    {
        return $this->belongsTo(RosterSlot::class);
    }
}

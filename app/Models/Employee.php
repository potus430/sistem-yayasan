<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasUuidV7;

    protected $fillable = ['branch_id', 'nip', 'name', 'type', 'base_salary', 'status'];

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

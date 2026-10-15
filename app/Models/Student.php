<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasUuidV7;

    protected $fillable = ['branch_id', 'nisn', 'name', 'current_class'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

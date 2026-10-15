<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolRoom extends Model
{
    use HasAuditLog, HasUuidV7;

    protected $table = 'school_rooms';

    protected $fillable = ['branch_id', 'name'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

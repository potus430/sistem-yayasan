<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'branch_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'http_method',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}

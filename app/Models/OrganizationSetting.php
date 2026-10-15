<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OrganizationSetting extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'application_name',
        'foundation_name',
        'foundation_address',
        'logo_path',
    ];

    public static function current(): self
    {
        return static::query()->first()
            ?? throw new LogicException('Organization settings have not been initialized.');
    }
}

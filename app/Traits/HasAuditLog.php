<?php

namespace App\Traits;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->recordModel(
                'created',
                $model,
                [],
                $model->getAttributes()
            );
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['created_at'], $changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $oldValues = [];
            foreach (array_keys($changes) as $attribute) {
                $oldValues[$attribute] = $model->getRawOriginal($attribute);
            }

            app(AuditLogger::class)->recordModel(
                'updated',
                $model,
                $oldValues,
                array_intersect_key($model->getAttributes(), $changes)
            );
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->recordModel(
                'deleted',
                $model,
                $model->getAttributes(),
                []
            );
        });
    }
}

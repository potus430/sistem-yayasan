<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class AuditLogger
{
    private const SENSITIVE_KEY_PATTERN = '/password|passphrase|token|secret|authorization|cookie|api[_-]?key|private[_-]?key/i';

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordModel(string $event, Model $model, array $oldValues, array $newValues): void
    {
        $attributes = $model->getAttributes();
        $branchId = $attributes['branch_id']
            ?? ($model->getTable() === 'branches' ? $model->getKey() : null);

        $this->record(
            $event,
            $model::class,
            (string) $model->getKey(),
            $branchId !== null ? (string) $branchId : null,
            $oldValues,
            $newValues
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $event,
        string $auditableType,
        string $auditableId,
        ?string $branchId,
        array $oldValues,
        array $newValues
    ): void {
        $this->recordMany([[
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'branch_id' => $branchId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]]);
    }

    /**
     * @param  array<int, array{
     *     event: string,
     *     auditable_type: string,
     *     auditable_id: string,
     *     branch_id: ?string,
     *     old_values: array<string, mixed>,
     *     new_values: array<string, mixed>
     * }>  $entries
     */
    public function recordMany(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $user = Auth::user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests()
            ? null
            : request();
        $actorName = $user?->name;
        $actorEmail = $user?->email;
        $actorRole = $user?->role;

        if ($user === null) {
            $isIntegration = $request?->is('api/sync/*') ?? false;
            $actorName = $isIntegration ? 'Integrasi API' : (app()->runningInConsole() ? 'Sistem/CLI' : 'Tidak terautentikasi');
            $actorRole = $isIntegration ? 'integration' : 'system';
        }

        $now = now();
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'id' => Uuid::uuid7()->toString(),
                'actor_user_id' => $user?->getAuthIdentifier(),
                'actor_name' => $actorName,
                'actor_email' => $actorEmail,
                'actor_role' => $actorRole,
                'branch_id' => $entry['branch_id'],
                'event' => $entry['event'],
                'auditable_type' => $entry['auditable_type'],
                'auditable_id' => $entry['auditable_id'],
                'old_values' => json_encode($this->sanitize($entry['old_values']), JSON_THROW_ON_ERROR),
                'new_values' => json_encode($this->sanitize($entry['new_values']), JSON_THROW_ON_ERROR),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->path(),
                'http_method' => $request?->method(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AuditLog::query()->insert($chunk);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $safeValues = [];

        foreach ($values as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key) === 1) {
                continue;
            }

            $safeValues[$key] = $this->sanitizeValue($value);
        }

        return $safeValues;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitize($value);
        }

        if (is_string($value) && in_array(substr($value, 0, 1), ['{', '['], true)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->sanitize($decoded);
            }
        }

        return $value;
    }
}

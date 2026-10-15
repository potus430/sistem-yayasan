<?php

namespace App\Jobs;

use App\Models\AnnualIncentiveAssignment;
use App\Models\Attendance;
use App\Models\BranchIncentive;
use App\Models\BranchPosition;
use App\Models\EmployeePositionAssignment;
use App\Models\Salary;
use App\Models\SppBilling;
use App\Models\SppPayment;
use App\Models\Student;
use App\Models\TeacherSubstitution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use LogicException;

class SyncDataToCentral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60, 180, 300];
    }

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('sync.central_url'), '/');
        $token = (string) config('sync.api_token');

        if ($baseUrl === '' || $token === '') {
            throw new LogicException('SYNC_CENTRAL_URL and SYNC_API_TOKEN must be configured before syncing.');
        }

        foreach ([
            'branch_incentives' => BranchIncentive::class,
            'annual_incentive_assignments' => AnnualIncentiveAssignment::class,
            'branch_positions' => BranchPosition::class,
            'employee_position_assignments' => EmployeePositionAssignment::class,
            'students' => Student::class,
            'spp_billings' => SppBilling::class,
            'spp_payments' => SppPayment::class,
            'salaries' => Salary::class,
            'attendances' => Attendance::class,
            'teacher_substitutions' => TeacherSubstitution::class,
        ] as $entity => $modelClass) {
            $this->syncModel($baseUrl, $token, $entity, $modelClass);
        }
    }

    private function syncModel(string $baseUrl, string $token, string $entity, string $modelClass): void
    {
        $batchSize = max(1, min(2000, (int) config('sync.batch_size', 500)));

        $modelClass::query()
            ->where(function ($query): void {
                $query->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at');
            })
            ->orderBy('updated_at')
            ->chunkById($batchSize, function ($models) use ($baseUrl, $token, $entity): void {
                $records = $models->map(function ($model): array {
                    $record = $model->getAttributes();
                    foreach (['incentive_details', 'allowance_details', 'position_details'] as $jsonColumn) {
                        if (isset($record[$jsonColumn]) && is_string($record[$jsonColumn])) {
                            $record[$jsonColumn] = json_decode($record[$jsonColumn], true, 512, JSON_THROW_ON_ERROR);
                        }
                    }

                    return $record;
                })->all();
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->post("{$baseUrl}/api/sync/{$entity}", ['records' => $records]);

                $response->throw();

                $syncedAt = Carbon::now();
                $models->first()->newQuery()
                    ->whereIn('id', $models->modelKeys())
                    ->update(['synced_at' => $syncedAt]);
            }, 'id');
    }
}

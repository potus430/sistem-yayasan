<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SyncIngestController
{
    private const ENTITIES = [
        'branch_incentives' => [
            'columns' => ['id', 'branch_id', 'name', 'calculation_type', 'default_amount', 'is_active', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'name', 'calculation_type', 'default_amount', 'is_active', 'created_at', 'updated_at'],
        ],
        'annual_incentive_assignments' => [
            'columns' => ['id', 'branch_id', 'academic_year_id', 'employee_id', 'branch_incentive_id', 'amount', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'academic_year_id', 'employee_id', 'branch_incentive_id', 'amount', 'created_at', 'updated_at'],
        ],
        'branch_positions' => [
            'columns' => ['id', 'branch_id', 'academic_year_id', 'name', 'requires_class', 'monthly_salary_addition', 'monthly_allowance', 'monthly_incentive', 'is_active', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'academic_year_id', 'name', 'requires_class', 'monthly_salary_addition', 'monthly_allowance', 'monthly_incentive', 'is_active', 'created_at', 'updated_at'],
        ],
        'employee_position_assignments' => [
            'columns' => ['id', 'branch_id', 'academic_year_id', 'employee_id', 'branch_position_id', 'class_name', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'academic_year_id', 'employee_id', 'branch_position_id', 'created_at', 'updated_at'],
        ],
        'students' => [
            'columns' => ['id', 'branch_id', 'nisn', 'name', 'current_class', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'nisn', 'name', 'current_class', 'created_at', 'updated_at'],
        ],
        'spp_billings' => [
            'columns' => ['id', 'student_id', 'branch_id', 'academic_year_id', 'month', 'amount', 'paid_amount', 'paid_at', 'status', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'student_id', 'branch_id', 'academic_year_id', 'month', 'amount', 'paid_amount', 'status', 'created_at', 'updated_at'],
        ],
        'spp_payments' => [
            'columns' => ['id', 'spp_billing_id', 'branch_id', 'received_by', 'amount', 'reference', 'payment_method', 'notes', 'paid_at', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'spp_billing_id', 'branch_id', 'amount', 'payment_method', 'paid_at', 'created_at', 'updated_at'],
        ],
        'salaries' => [
            'columns' => ['id', 'employee_id', 'branch_id', 'period', 'incentives', 'incentive_details', 'position_salary', 'position_details', 'allowances', 'allowance_details', 'deductions', 'net_salary', 'status', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'employee_id', 'branch_id', 'period', 'incentives', 'allowances', 'deductions', 'net_salary', 'status', 'created_at', 'updated_at'],
        ],
        'attendances' => [
            'columns' => ['id', 'employee_id', 'branch_id', 'date', 'status', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'employee_id', 'branch_id', 'date', 'status', 'created_at', 'updated_at'],
        ],
        'teacher_substitutions' => [
            'columns' => ['id', 'branch_id', 'roster_slot_id', 'absent_employee_id', 'substitute_employee_id', 'date', 'period', 'is_cancelled', 'created_at', 'updated_at', 'synced_at'],
            'required' => ['id', 'branch_id', 'roster_slot_id', 'absent_employee_id', 'substitute_employee_id', 'date', 'period', 'is_cancelled', 'created_at', 'updated_at'],
        ],
    ];

    public function __invoke(Request $request, string $entity): JsonResponse
    {
        $expectedToken = (string) config('sync.api_token');
        $givenToken = (string) $request->bearerToken();

        if ($expectedToken === '') {
            throw new ServiceUnavailableHttpException(null, 'Central sync token is not configured.');
        }

        abort_unless($givenToken !== '' && hash_equals($expectedToken, $givenToken), 401);
        abort_unless(isset(self::ENTITIES[$entity]), 404);

        $definition = self::ENTITIES[$entity];
        $rules = ['records' => ['required', 'array', 'min:1', 'max:2000']];

        foreach ($definition['columns'] as $column) {
            $rules["records.*.{$column}"] = in_array($column, $definition['required'], true)
                ? ['required']
                : ['nullable'];
        }

        $rules['records.*.id'][] = 'uuid';
        $rules['records.*.branch_id'][] = 'uuid';

        if (in_array($entity, ['annual_incentive_assignments', 'employee_position_assignments', 'salaries', 'attendances'], true)) {
            $rules['records.*.employee_id'][] = 'required|uuid';
        }
        if (in_array($entity, ['branch_positions', 'employee_position_assignments'], true)) {
            $rules['records.*.academic_year_id'][] = 'required|uuid';
        }
        if ($entity === 'branch_positions') {
            $rules['records.*.name'][] = 'required|string|max:100';
            $rules['records.*.requires_class'][] = 'boolean';
            $rules['records.*.is_active'][] = 'boolean';
            foreach (['monthly_salary_addition', 'monthly_allowance', 'monthly_incentive'] as $amount) {
                $rules["records.*.{$amount}"][] = 'numeric|min:0|max:999999999999.99';
            }
        }
        if ($entity === 'employee_position_assignments') {
            $rules['records.*.branch_position_id'][] = 'required|uuid';
            $rules['records.*.class_name'][] = 'nullable|string|max:50';
        }
        if (in_array($entity, ['spp_billings', 'spp_payments'], true)) {
            $rules['records.*.student_id'][] = $entity === 'spp_billings' ? 'required|uuid' : 'nullable|uuid';
        }
        if ($entity === 'spp_billings') {
            $rules['records.*.academic_year_id'][] = 'required|uuid';
            $rules['records.*.month'][] = 'integer|between:1,12';
            $rules['records.*.amount'][] = 'numeric|min:0|max:999999999999.99';
            $rules['records.*.paid_amount'][] = 'numeric|min:0|max:999999999999.99';
            $rules['records.*.status'][] = ['in:UNPAID,PAID'];
            $rules['records.*.paid_at'][] = 'nullable|date';
        }
        if ($entity === 'spp_payments') {
            $rules['records.*.spp_billing_id'][] = 'required|uuid';
            $rules['records.*.received_by'][] = 'nullable|uuid';
            $rules['records.*.amount'][] = 'numeric|gt:0|max:999999999999.99';
            $rules['records.*.payment_method'][] = ['in:TUNAI,TRANSFER,QRIS,LAINNYA'];
            $rules['records.*.reference'][] = 'nullable|string|max:100';
            $rules['records.*.notes'][] = 'nullable|string|max:1000';
            $rules['records.*.paid_at'][] = 'date';
        }
        if ($entity === 'branch_incentives') {
            $rules['records.*.name'][] = 'string|max:100';
            $rules['records.*.calculation_type'][] = ['in:ANNUAL,MANUAL'];
            $rules['records.*.default_amount'][] = 'numeric|min:0|max:999999999999.99';
            $rules['records.*.is_active'][] = 'boolean';
        }
        if ($entity === 'annual_incentive_assignments') {
            $rules['records.*.academic_year_id'][] = 'required|uuid';
            $rules['records.*.branch_incentive_id'][] = 'required|uuid';
            $rules['records.*.amount'][] = 'numeric|min:0|max:999999999999.99';
        }
        if ($entity === 'students') {
            $rules['records.*.nisn'][] = 'string|max:30';
            $rules['records.*.name'][] = 'string|max:255';
            $rules['records.*.current_class'][] = 'string|max:50';
        }
        if ($entity === 'salaries') {
            $rules['records.*.period'][] = ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];
            $rules['records.*.status'][] = ['in:PENDING,PAID'];
            $rules['records.*.incentive_details'][] = ['nullable', 'array', 'max:100'];
            $rules['records.*.allowance_details'][] = ['nullable', 'array', 'max:100'];
            $rules['records.*.position_details'][] = ['nullable', 'array', 'max:100'];
            foreach (['incentives', 'position_salary', 'allowances', 'deductions', 'net_salary'] as $amount) {
                $rules["records.*.{$amount}"][] = 'numeric|min:0|max:999999999999.99';
            }
            $rules['records.*.incentive_details.*.branch_incentive_id'][] = ['nullable', 'uuid'];
            $rules['records.*.incentive_details.*.name'][] = ['required', 'string', 'max:100'];
            $rules['records.*.incentive_details.*.amount'][] = ['required', 'numeric', 'min:0', 'max:999999999999.99'];
            $rules['records.*.allowance_details.*.name'][] = ['required', 'string', 'max:100'];
            $rules['records.*.allowance_details.*.type'][] = ['required', 'in:TRANSPORT,SUBSTITUTION,MANUAL,POSITION'];
            $rules['records.*.allowance_details.*.amount'][] = ['required', 'numeric', 'min:0', 'max:999999999999.99'];
            $rules['records.*.allowance_details.*.count'][] = ['nullable', 'integer', 'min:0'];
            $rules['records.*.allowance_details.*.rate'][] = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
            $rules['records.*.position_details.*.position_id'][] = ['required', 'uuid'];
            $rules['records.*.position_details.*.name'][] = ['required', 'string', 'max:100'];
            $rules['records.*.position_details.*.class_name'][] = ['nullable', 'string', 'max:50'];
            foreach (['salary_addition', 'allowance', 'incentive'] as $amount) {
                $rules["records.*.position_details.*.{$amount}"][] = 'required|numeric|min:0|max:999999999999.99';
            }
        }
        if ($entity === 'attendances') {
            $rules['records.*.status'][] = ['in:HADIR,IZIN,ALFA,SAKIT'];
            $rules['records.*.date'][] = ['date'];
        }
        if ($entity === 'teacher_substitutions') {
            $rules['records.*.roster_slot_id'][] = 'required|uuid';
            $rules['records.*.absent_employee_id'][] = 'required|uuid';
            $rules['records.*.substitute_employee_id'][] = 'required|uuid';
            $rules['records.*.date'][] = 'date';
            $rules['records.*.period'][] = 'integer|min:1|max:8';
            $rules['records.*.is_cancelled'][] = 'boolean';
        }
        foreach (['created_at', 'updated_at'] as $timestamp) {
            $rules["records.*.{$timestamp}"][] = 'date';
        }
        $rules['records.*.synced_at'][] = 'date';

        foreach ($rules as $attribute => $attributeRules) {
            $normalizedRules = [];
            foreach ($attributeRules as $rule) {
                if (is_array($rule)) {
                    array_push($normalizedRules, ...$rule);
                } elseif (str_contains($rule, '|') && ! str_starts_with($rule, 'regex:')) {
                    array_push($normalizedRules, ...explode('|', $rule));
                } else {
                    $normalizedRules[] = $rule;
                }
            }
            $rules[$attribute] = $normalizedRules;
        }

        $validated = Validator::make($request->all(), $rules)->validate();
        $rows = array_map(function (array $record) use ($definition, $entity): array {
            $row = array_replace(
                array_fill_keys($definition['columns'], null),
                array_intersect_key($record, array_flip($definition['columns']))
            );
            if ($entity === 'salaries') {
                foreach (['incentive_details', 'allowance_details', 'position_details'] as $jsonColumn) {
                    if (isset($row[$jsonColumn])) {
                        $row[$jsonColumn] = json_encode($row[$jsonColumn], JSON_THROW_ON_ERROR);
                    }
                }
            }

            return $row;
        }, $validated['records']);
        $updates = array_values(array_diff($definition['columns'], ['id', 'created_at', 'synced_at']));

        DB::table($entity)->upsert($rows, ['id'], $updates);

        return response()->json(['accepted' => count($rows)]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Salary;
use App\Services\PayrollExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExcelController
{
    public function __invoke(Request $request, PayrollExcelExporter $exporter): StreamedResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin_pusat', 'keuangan'], true), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
        ]);
        $branchId = $user->role === 'admin_pusat'
            ? ($validated['branch_id'] ?? null)
            : ($user->branch_id ?: null);

        $salaries = Salary::query()
            ->with(['employee.branch'])
            ->join('employees', 'employees.id', '=', 'salaries.employee_id')
            ->join('branches', 'branches.id', '=', 'salaries.branch_id')
            ->select('salaries.*')
            ->where('salaries.period', $validated['period'])
            ->when($branchId, fn ($query) => $query->where('salaries.branch_id', $branchId))
            ->orderBy('branches.name')
            ->orderBy('employees.name')
            ->get();

        if ($salaries->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'Belum ada data payroll untuk periode dan cabang yang dipilih.',
            ]);
        }

        $branchName = $branchId ? Branch::query()->whereKey($branchId)->value('name') : null;
        $path = $exporter->export($salaries, $validated['period'], $branchName);
        $branchSuffix = $branchName ? '-'.str($branchName)->slug() : '-semua-cabang';

        return response()->streamDownload(function () use ($path): void {
            try {
                $input = fopen($path, 'rb');
                if ($input === false) {
                    throw new \RuntimeException('Unable to open the generated payroll workbook.');
                }

                fpassthru($input);
                fclose($input);
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }, "payroll-{$validated['period']}{$branchSuffix}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

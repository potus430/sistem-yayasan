<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\OrganizationSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PayrollSlipPdfController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['keuangan_pusat', 'keuangan'], true), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'per_page' => ['nullable', 'integer', 'in:1,2,4'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 2);
        $branchId = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true)
            ? ($validated['branch_id'] ?? null)
            : ($user->branch_id ?: null);

        $salaries = Salary::query()
            ->with(['employee.branch'])
            ->join('employees', 'employees.id', '=', 'salaries.employee_id')
            ->join('branches', 'branches.id', '=', 'salaries.branch_id')
            ->select('salaries.*')
            ->where('salaries.period', $validated['period'])
            ->when($branchId, fn ($query) => $query->where('salaries.branch_id', $branchId))
            ->when($validated['employee_id'] ?? null, fn ($query, string $employeeId) => $query->where('salaries.employee_id', $employeeId))
            ->orderBy('branches.name')
            ->orderBy('employees.name')
            ->get();

        if ($salaries->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'Belum ada data payroll untuk periode dan cabang yang dipilih.',
            ]);
        }

        $salaryPages = $salaries
            ->chunk($perPage)
            ->map(fn ($page) => $page->chunk(2));
        $organizationSettings = OrganizationSetting::current();
        $logoDataUri = null;
        if ($organizationSettings->logo_path !== null) {
            $disk = Storage::disk('public');
            $logoPath = $disk->path($organizationSettings->logo_path);
            $logoContents = file_get_contents($logoPath);
            if ($logoContents === false) {
                throw new \RuntimeException('Unable to read the foundation logo for the payroll PDF.');
            }
            $logoDataUri = 'data:'.$disk->mimeType($organizationSettings->logo_path).';base64,'.base64_encode($logoContents);
        }

        $pdf = Pdf::loadView('reports.payroll-slips', [
            'salaryPages' => $salaryPages,
            'period' => $validated['period'],
            'generatedAt' => now(),
            'salaryCount' => $salaries->count(),
            'perPage' => $perPage,
            'organizationSettings' => $organizationSettings,
            'organizationLogoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'portrait')->setOptions([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $branchName = $branchId ? $salaries->first()?->employee?->branch?->name : null;
        $branchSuffix = $branchName ? '-'.str($branchName)->slug() : '-semua-cabang';
        $employeeSuffix = isset($validated['employee_id']) ? '-pegawai' : '';
        $filename = "slip-gaji-{$validated['period']}{$branchSuffix}{$employeeSuffix}";

        return isset($validated['employee_id'])
            ? $pdf->stream($filename.'.pdf')
            : $pdf->download($filename.'.pdf');
    }
}

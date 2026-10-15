<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController
{
    public function __invoke(Request $request, string $report): StreamedResponse
    {
        $user = $request->user();
        $financeReports = ['payroll', 'spp'];
        $schoolReports = ['students', 'employees', 'attendance'];
        abort_unless(in_array($report, array_merge($financeReports, $schoolReports), true), 404);
        abort_unless(
            in_array($user->role, ['admin_pusat'], true)
                || (in_array($report, $financeReports, true) && $user->role === 'keuangan')
                || (in_array($report, $schoolReports, true) && $user->role === 'admin_cabang'),
            403
        );

        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'period' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $branchId = $user->role === 'admin_pusat'
            ? ($validated['branch_id'] ?? null)
            : ($user->branch_id ?: null);

        [$query, $headers, $filename] = $this->reportQuery($report, $branchId, $validated);
        if ($branchId) {
            $branchName = Branch::query()->whereKey($branchId)->value('name');
            $filename = "laporan-{$report}-".str($branchName)->slug().'-'.now()->format('Ymd-His').'.csv';
        }

        return response()->streamDownload(function () use ($query, $headers): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '\\');
            foreach ($query->cursor() as $row) {
                $values = array_values((array) $row);
                if (isset($row->position_details) && is_string($row->position_details)) {
                    $positions = json_decode($row->position_details, true, 512, JSON_THROW_ON_ERROR);
                    $values[array_search('position_details', array_keys((array) $row), true)] = collect($positions)
                        ->map(fn (array $detail): string => ($detail['name'] ?? 'Jabatan').' = '.number_format((float) ($detail['salary_addition'] ?? 0), 2, '.', ''))
                        ->implode('; ');
                }
                fputcsv($stream, array_map(fn ($value) => $this->spreadsheetSafe($value), $values), ',', '"', '\\');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{Builder, array<int, string>, string}
     */
    private function reportQuery(string $report, ?string $branchId, array $filters): array
    {
        $query = match ($report) {
            'students' => DB::table('students')
                ->join('branches', 'branches.id', '=', 'students.branch_id')
                ->select('branches.name as branch', 'students.nisn', 'students.name', 'students.current_class')
                ->when($branchId, fn (Builder $query) => $query->where('students.branch_id', $branchId))
                ->orderBy('branches.name')->orderBy('students.current_class')->orderBy('students.name'),
            'employees' => DB::table('employees')
                ->join('branches', 'branches.id', '=', 'employees.branch_id')
                ->select('branches.name as branch', 'employees.nip', 'employees.name', 'employees.type', 'employees.status', 'employees.base_salary')
                ->when($branchId, fn (Builder $query) => $query->where('employees.branch_id', $branchId))
                ->orderBy('branches.name')->orderBy('employees.name'),
            'attendance' => DB::table('attendances')
                ->join('branches', 'branches.id', '=', 'attendances.branch_id')
                ->join('employees', 'employees.id', '=', 'attendances.employee_id')
                ->select('branches.name as branch', 'employees.nip', 'employees.name', 'attendances.date', 'attendances.status')
                ->when($branchId, fn (Builder $query) => $query->where('attendances.branch_id', $branchId))
                ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('attendances.date', '>=', $from))
                ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('attendances.date', '<=', $to))
                ->orderBy('attendances.date')->orderBy('employees.name'),
            'payroll' => DB::table('salaries')
                ->join('branches', 'branches.id', '=', 'salaries.branch_id')
                ->join('employees', 'employees.id', '=', 'salaries.employee_id')
                ->select('branches.name as branch', 'salaries.period', 'employees.nip', 'employees.name', 'employees.base_salary', 'salaries.position_salary', 'salaries.position_details', 'salaries.incentives', 'salaries.allowances', 'salaries.allowance_details', 'salaries.deductions', 'salaries.net_salary', 'salaries.status')
                ->when($branchId, fn (Builder $query) => $query->where('salaries.branch_id', $branchId))
                ->when($filters['period'] ?? null, fn (Builder $query, string $period) => $query->where('salaries.period', $period))
                ->orderBy('salaries.period')->orderBy('branches.name')->orderBy('employees.name'),
            'spp' => DB::table('spp_billings')
                ->join('branches', 'branches.id', '=', 'spp_billings.branch_id')
                ->join('students', 'students.id', '=', 'spp_billings.student_id')
                ->join('academic_years', 'academic_years.id', '=', 'spp_billings.academic_year_id')
                ->select('branches.name as branch', 'academic_years.name as academic_year', 'students.nisn', 'students.name', 'students.current_class', 'spp_billings.month', 'spp_billings.amount', 'spp_billings.paid_amount', 'spp_billings.status', 'spp_billings.paid_at')
                ->when($branchId, fn (Builder $query) => $query->where('spp_billings.branch_id', $branchId))
                ->orderBy('branches.name')->orderBy('academic_years.starts_on')->orderBy('students.name')->orderBy('spp_billings.month'),
        };

        $headers = [
            'students' => ['Cabang', 'NISN', 'Nama siswa', 'Kelas'],
            'employees' => ['Cabang', 'NIP', 'Nama pegawai', 'Jenis', 'Status', 'Gaji pokok'],
            'attendance' => ['Cabang', 'NIP', 'Nama pegawai', 'Tanggal', 'Status'],
            'payroll' => ['Cabang', 'Periode', 'NIP', 'Nama pegawai', 'Gaji pokok', 'Tambahan gaji jabatan', 'Rincian jabatan', 'Insentif', 'Tunjangan', 'Rincian tunjangan', 'Potongan', 'Gaji bersih', 'Status'],
            'spp' => ['Cabang', 'Tahun ajaran', 'NISN', 'Nama siswa', 'Kelas', 'Bulan', 'Tagihan', 'Terbayar', 'Status', 'Tanggal lunas'],
        ][$report];

        return [$query, $headers, "laporan-{$report}-".now()->format('Ymd-His').'.csv'];
    }

    private function spreadsheetSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}

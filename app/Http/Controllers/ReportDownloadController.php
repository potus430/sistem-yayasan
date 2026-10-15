<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\TabularExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportDownloadController
{
    public function __invoke(Request $request, string $report, TabularExcelExporter $excelExporter): Response
    {
        $user = $request->user();
        $financeReports = ['payroll', 'spp', 'position-assignments'];
        $schoolReports = ['students', 'employees', 'attendance', 'roster', 'position-assignments'];
        $adminReports = ['users', 'activity-logs', 'branch-summary'];
        abort_unless(in_array($report, array_merge($financeReports, $schoolReports, $adminReports), true), 404);
        abort_unless(
            ($report === 'payroll' && in_array($user->role, ['keuangan_pusat', 'keuangan'], true))
                || ($report === 'spp' && in_array($user->role, ['admin_pusat', 'keuangan_pusat', 'keuangan'], true))
                || ($report === 'position-assignments' && in_array($user->role, ['admin_pusat', 'admin_cabang', 'keuangan_pusat', 'keuangan'], true))
                || (in_array($report, $adminReports, true) && $user->role === 'admin_pusat')
                || ($report === 'branch-summary' && in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true))
                || (in_array($report, $schoolReports, true) && in_array($user->role, ['admin_pusat', 'admin_cabang'], true)),
            403
        );

        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'period' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'format' => ['nullable', 'in:csv,pdf,xlsx'],
        ]);
        $format = $validated['format'] ?? 'csv';
        $branchId = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true)
            ? ($validated['branch_id'] ?? null)
            : ($user->branch_id ?: null);

        [$query, $headers, $filename, $columns] = $this->reportQuery($report, $branchId, $validated, $user->role === 'admin_pusat');
        $dateColumns = [
            'students' => 'students.created_at',
            'employees' => 'employees.created_at',
            'users' => 'users.created_at',
            'activity-logs' => 'audit_logs.created_at',
            'spp' => 'spp_billings.created_at',
        ];
        if (isset($dateColumns[$report])) {
            $query
                ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate($dateColumns[$report], '>=', $from))
                ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate($dateColumns[$report], '<=', $to));
        }
        if ($report === 'payroll') {
            $query
                ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->where('salaries.period', '>=', substr($from, 0, 7)))
                ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->where('salaries.period', '<=', substr($to, 0, 7)));
        }
        if ($branchId) {
            $branchName = Branch::query()->whereKey($branchId)->value('name');
            $filename = "laporan-{$report}-".str($branchName)->slug().'-'.now()->format('Ymd-His');
        }

        if ($format === 'pdf') {
            $rows = $query->get()->map(function ($row) use ($columns): object {
                $formatted = [];
                foreach ($columns as $column) {
                    $formatted[$column] = $this->exportValue($row->{$column} ?? null, $column);
                }

                return (object) $formatted;
            });

            return Pdf::loadView('reports.table', [
                'title' => $this->reportTitle($report),
                'headers' => $headers,
                'columns' => $columns,
                'rows' => $rows,
            ])->setPaper('a4', 'landscape')->setOptions([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ])->download($filename.'.pdf');
        }

        if ($format === 'xlsx') {
            $rows = $query->get()->map(fn ($row): array => array_map(
                fn (string $column): mixed => $this->exportValue($row->{$column} ?? null, $column),
                $columns
            ));
            $path = $excelExporter->export($headers, $rows, $report);

            return response()->streamDownload(function () use ($path): void {
                try {
                    $input = fopen($path, 'rb');
                    if ($input === false) {
                        throw new \RuntimeException('Unable to open the generated report workbook.');
                    }
                    fpassthru($input);
                    fclose($input);
                } finally {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }, $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($query, $headers, $columns): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '\\');
            foreach ($query->cursor() as $row) {
                $values = array_map(fn (string $column): mixed => $this->exportValue($row->{$column} ?? null, $column), $columns);
                fputcsv($stream, array_map(fn ($value) => $this->spreadsheetSafe($value), $values), ',', '"', '\\');
            }
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function branchSummaryQuery(array $filters): Builder
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();
        $period = $filters['period'] ?? now()->format('Y-m');

        return DB::table('branches')
            ->select('branches.name as branch', 'branches.level')
            ->selectSub(DB::table('students')->selectRaw('COUNT(*)')->whereColumn('students.branch_id', 'branches.id'), 'students')
            ->selectSub(DB::table('employees')->selectRaw('COUNT(*)')->whereColumn('employees.branch_id', 'branches.id'), 'employees')
            ->selectSub(DB::table('attendances')->selectRaw("COALESCE(SUM(CASE WHEN status = 'HADIR' THEN 1 ELSE 0 END), 0)")->whereColumn('attendances.branch_id', 'branches.id')->whereBetween('date', [$from, $to]), 'present')
            ->selectSub(DB::table('attendances')->selectRaw("COALESCE(SUM(CASE WHEN status <> 'HADIR' THEN 1 ELSE 0 END), 0)")->whereColumn('attendances.branch_id', 'branches.id')->whereBetween('date', [$from, $to]), 'absent')
            ->selectSub(DB::table('salaries')->selectRaw("COALESCE(SUM(CASE WHEN status = 'PAID' THEN net_salary ELSE 0 END), 0)")->whereColumn('salaries.branch_id', 'branches.id')->where('period', $period), 'payroll_paid')
            ->selectSub(DB::table('salaries')->selectRaw("COALESCE(SUM(CASE WHEN status = 'PENDING' THEN net_salary ELSE 0 END), 0)")->whereColumn('salaries.branch_id', 'branches.id')->where('period', $period), 'payroll_pending')
            ->selectSub(DB::table('spp_billings')->selectRaw('COALESCE(SUM(paid_amount), 0)')->whereColumn('spp_billings.branch_id', 'branches.id')->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $yearId) => $query->where('academic_year_id', $yearId)), 'spp_collected')
            ->selectSub(DB::table('spp_billings')->selectRaw('COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0)')->whereColumn('spp_billings.branch_id', 'branches.id')->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $yearId) => $query->where('academic_year_id', $yearId)), 'spp_outstanding')
            ->orderBy('branches.name');
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{Builder, array<int, string>, string, array<int, string>}
     */
    private function reportQuery(string $report, ?string $branchId, array $filters, bool $centralAdmin = false): array
    {
        $query = match ($report) {
            'students' => DB::table('students')
                ->join('branches', 'branches.id', '=', 'students.branch_id')
                ->select('branches.name as branch', 'students.nisn', 'students.name', 'students.current_class')
                ->when($branchId, fn (Builder $query) => $query->where('students.branch_id', $branchId))
                ->orderBy('branches.name')->orderBy('students.current_class')->orderBy('students.name'),
            'employees' => DB::table('employees')
                ->join('branches', 'branches.id', '=', 'employees.branch_id')
                ->select('branches.name as branch', 'employees.nip', 'employees.name', 'employees.type', 'employees.status')
                ->selectRaw("CASE WHEN employees.type = 'STAFF' THEN employees.base_salary ELSE NULL END as base_salary")
                ->when($branchId, fn (Builder $query) => $query->where('employees.branch_id', $branchId))
                ->when($centralAdmin, fn (Builder $query) => $query->where('employees.type', 'STAFF'))
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
                ->select('branches.name as branch', 'salaries.period', 'employees.nip', 'employees.name', 'salaries.base_salary', 'salaries.teaching_hours', 'salaries.hourly_rate', 'salaries.position_salary', 'salaries.position_details', 'salaries.incentives', 'salaries.allowances', 'salaries.allowance_details', 'salaries.deductions', 'salaries.net_salary', 'salaries.status')
                ->when($branchId, fn (Builder $query) => $query->where('salaries.branch_id', $branchId))
                ->when($filters['period'] ?? null, fn (Builder $query, string $period) => $query->where('salaries.period', $period))
                ->orderBy('salaries.period')->orderBy('branches.name')->orderBy('employees.name'),
            'spp' => DB::table('spp_billings')
                ->join('branches', 'branches.id', '=', 'spp_billings.branch_id')
                ->join('students', 'students.id', '=', 'spp_billings.student_id')
                ->join('academic_years', 'academic_years.id', '=', 'spp_billings.academic_year_id')
                ->select('branches.name as branch', 'academic_years.name as academic_year', 'students.nisn', 'students.name', 'students.current_class', 'spp_billings.month', 'spp_billings.amount', 'spp_billings.paid_amount', 'spp_billings.status', 'spp_billings.paid_at')
                ->when($branchId, fn (Builder $query) => $query->where('spp_billings.branch_id', $branchId))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $yearId) => $query->where('spp_billings.academic_year_id', $yearId))
                ->when($filters['month'] ?? null, fn (Builder $query, int $month) => $query->where('spp_billings.month', $month))
                ->orderBy('branches.name')->orderBy('academic_years.starts_on')->orderBy('students.name')->orderBy('spp_billings.month'),
            'roster' => DB::table('roster_slots')
                ->join('branches', 'branches.id', '=', 'roster_slots.branch_id')
                ->join('academic_years', 'academic_years.id', '=', 'roster_slots.academic_year_id')
                ->join('employees', 'employees.id', '=', 'roster_slots.employee_id')
                ->select('branches.name as branch', 'academic_years.name as academic_year', 'roster_slots.weekday', 'roster_slots.period', 'roster_slots.class_name', 'roster_slots.subject', 'employees.name as employee', 'roster_slots.room')
                ->when($branchId, fn (Builder $query) => $query->where('roster_slots.branch_id', $branchId))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $yearId) => $query->where('roster_slots.academic_year_id', $yearId))
                ->orderBy('branches.name')->orderBy('roster_slots.weekday')->orderBy('roster_slots.period')->orderBy('roster_slots.class_name'),
            'position-assignments' => DB::table('employee_position_assignments')
                ->join('branches', 'branches.id', '=', 'employee_position_assignments.branch_id')
                ->join('academic_years', 'academic_years.id', '=', 'employee_position_assignments.academic_year_id')
                ->join('branch_positions', 'branch_positions.id', '=', 'employee_position_assignments.branch_position_id')
                ->join('employees', 'employees.id', '=', 'employee_position_assignments.employee_id')
                ->select('branches.name as branch', 'academic_years.name as academic_year', 'branch_positions.name as position', 'employees.nip', 'employees.name as employee', 'employee_position_assignments.class_name', 'branch_positions.monthly_salary_addition', 'branch_positions.monthly_allowance', 'branch_positions.monthly_incentive')
                ->when($branchId, fn (Builder $query) => $query->where('employee_position_assignments.branch_id', $branchId))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $yearId) => $query->where('employee_position_assignments.academic_year_id', $yearId))
                ->orderBy('branches.name')->orderBy('branch_positions.name')->orderBy('employees.name'),
            'users' => DB::table('users')
                ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
                ->select('users.name', 'users.email', 'users.role', 'branches.name as branch', 'users.is_active')
                ->orderBy('users.role')->orderBy('users.name'),
            'activity-logs' => DB::table('audit_logs')
                ->select('created_at', 'actor_name', 'actor_email', 'actor_role', 'ip_address', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values')
                ->orderByDesc('created_at'),
            'branch-summary' => $this->branchSummaryQuery($filters),
        };

        $headers = [
            'students' => ['Cabang', 'NISN', 'Nama siswa', 'Kelas'],
            'employees' => ['Cabang', 'NIP', 'Nama pegawai', 'Jenis', 'Status', 'Gaji pokok'],
            'attendance' => ['Cabang', 'NIP', 'Nama pegawai', 'Tanggal', 'Status'],
            'payroll' => ['Cabang', 'Periode', 'NIP', 'Nama pegawai', 'Gaji pokok', 'Jam mengajar', 'Tarif per jam', 'Tambahan gaji jabatan', 'Rincian jabatan', 'Insentif', 'Tunjangan', 'Rincian tunjangan', 'Potongan', 'Gaji bersih', 'Status'],
            'spp' => ['Cabang', 'Tahun ajaran', 'NISN', 'Nama siswa', 'Kelas', 'Bulan', 'Tagihan', 'Terbayar', 'Status', 'Tanggal lunas'],
            'roster' => ['Cabang', 'Tahun ajaran', 'Hari', 'Jam', 'Kelas', 'Mata pelajaran', 'Guru', 'Ruang'],
            'position-assignments' => ['Cabang', 'Tahun ajaran', 'Jabatan', 'NIP', 'Pegawai', 'Kelas', 'Tambahan gaji', 'Tunjangan', 'Insentif'],
            'users' => ['Nama', 'Email', 'Peran', 'Cabang', 'Aktif'],
            'activity-logs' => ['Waktu', 'Nama pengguna', 'Email pengguna', 'Peran', 'Alamat IP', 'Aktivitas', 'Jenis data', 'ID data', 'Nilai sebelum', 'Nilai sesudah'],
            'branch-summary' => ['Cabang', 'Jenjang', 'Siswa', 'Pegawai', 'Hadir', 'Tidak hadir', 'Payroll dibayar', 'Payroll menunggu', 'SPP terkumpul', 'SPP sisa'],
        ][$report];

        $columns = [
            'students' => ['branch', 'nisn', 'name', 'current_class'],
            'employees' => ['branch', 'nip', 'name', 'type', 'status', 'base_salary'],
            'attendance' => ['branch', 'nip', 'name', 'date', 'status'],
            'payroll' => ['branch', 'period', 'nip', 'name', 'base_salary', 'teaching_hours', 'hourly_rate', 'position_salary', 'position_details', 'incentives', 'allowances', 'allowance_details', 'deductions', 'net_salary', 'status'],
            'spp' => ['branch', 'academic_year', 'nisn', 'name', 'current_class', 'month', 'amount', 'paid_amount', 'status', 'paid_at'],
            'roster' => ['branch', 'academic_year', 'weekday', 'period', 'class_name', 'subject', 'employee', 'room'],
            'position-assignments' => ['branch', 'academic_year', 'position', 'nip', 'employee', 'class_name', 'monthly_salary_addition', 'monthly_allowance', 'monthly_incentive'],
            'users' => ['name', 'email', 'role', 'branch', 'is_active'],
            'activity-logs' => ['created_at', 'actor_name', 'actor_email', 'actor_role', 'ip_address', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values'],
            'branch-summary' => ['branch', 'level', 'students', 'employees', 'present', 'absent', 'payroll_paid', 'payroll_pending', 'spp_collected', 'spp_outstanding'],
        ][$report];

        return [$query, $headers, "laporan-{$report}-".now()->format('Ymd-His'), $columns];
    }

    private function reportTitle(string $report): string
    {
        return [
            'students' => 'Laporan Data Siswa',
            'employees' => 'Laporan Guru & Pegawai',
            'attendance' => 'Laporan Absensi',
            'payroll' => 'Laporan Payroll',
            'spp' => 'Laporan Pembayaran SPP',
            'roster' => 'Laporan Roster Pelajaran',
            'position-assignments' => 'Laporan Penugasan Jabatan',
            'users' => 'Laporan Akun Pengguna',
            'activity-logs' => 'Laporan Log Aktivitas',
            'branch-summary' => 'Ikhtisar Seluruh Cabang',
        ][$report];
    }

    private function exportValue(mixed $value, string $column): mixed
    {
        if (in_array($column, [
            'base_salary', 'position_salary', 'monthly_salary_addition', 'monthly_allowance',
            'monthly_incentive', 'incentives', 'allowances', 'deductions', 'net_salary',
            'amount', 'paid_amount', 'weekday', 'period',
            'students', 'employees', 'present', 'absent', 'payroll_paid', 'payroll_pending',
            'spp_collected', 'spp_outstanding',
        ], true) && is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (! in_array($column, ['position_details', 'allowance_details', 'old_values', 'new_values'], true)) {
            return $value;
        }

        $details = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($details)) {
            return (string) $value;
        }
        if (in_array($column, ['old_values', 'new_values'], true)) {
            return json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return collect($details)->map(function (array $detail): string {
            $name = $detail['name'] ?? 'Rincian';
            $amount = number_format((float) ($detail['amount'] ?? $detail['salary_addition'] ?? 0), 2, '.', '');

            return "{$name} = {$amount}";
        })->implode('; ');
    }

    private function spreadsheetSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}

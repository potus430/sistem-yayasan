<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ReportsDashboard extends Component
{
    public string $branchId = '';

    public string $period = '';

    public string $academicYearId = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->branchId = $user->role === 'admin_pusat' ? '' : (string) $user->branch_id;
        $this->period = now()->format('Y-m');
        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
    }

    public function updatedPeriod(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->period = now()->format('Y-m');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isCentralAdmin = $user->role === 'admin_pusat';
        $canViewSchool = in_array($user->role, ['admin_pusat', 'admin_cabang'], true);
        $canViewFinance = in_array($user->role, ['admin_pusat', 'keuangan'], true);
        $branchId = $isCentralAdmin
            ? (Branch::query()->whereKey($this->branchId)->value('id') ?: null)
            : $user->branch_id;
        $periodDate = Carbon::createFromFormat('!Y-m', $this->period);
        $from = $periodDate->copy()->startOfMonth()->toDateString();
        $to = $periodDate->copy()->endOfMonth()->toDateString();
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_active']);
        $academicYearId = $academicYears->contains('id', $this->academicYearId)
            ? $this->academicYearId
            : (string) ($academicYears->firstWhere('is_active', true)?->id ?? '');
        $this->academicYearId = $academicYearId;

        $school = $canViewSchool ? $this->schoolMetrics($branchId, $from, $to) : null;
        $finance = $canViewFinance ? $this->financeMetrics($branchId, $this->period, $academicYearId) : null;
        $branches = $isCentralAdmin ? Branch::query()->orderBy('name')->get(['id', 'name', 'level']) : collect();
        $branchSummaries = $isCentralAdmin && ! $branchId
            ? $this->branchSummaries($from, $to, $this->period, $academicYearId)
            : collect();

        return view('livewire.reports-dashboard', compact(
            'isCentralAdmin',
            'canViewSchool',
            'canViewFinance',
            'branches',
            'branchId',
            'periodDate',
            'academicYears',
            'academicYearId',
            'school',
            'finance',
            'branchSummaries'
        ))->layout('layouts.app', ['title' => 'Laporan']);
    }

    /**
     * @return array<string, int|float>
     */
    private function schoolMetrics(?string $branchId, string $from, string $to): array
    {
        $students = Student::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();
        $employees = Employee::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN type = 'GURU' THEN 1 ELSE 0 END) AS teachers, SUM(CASE WHEN type = 'STAFF' THEN 1 ELSE 0 END) AS staff")
            ->first();
        $attendance = DB::table('attendances')
            ->whereBetween('date', [$from, $to])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'HADIR' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN status = 'IZIN' THEN 1 ELSE 0 END) AS permission, SUM(CASE WHEN status = 'ALFA' THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN status = 'SAKIT' THEN 1 ELSE 0 END) AS sick")
            ->first();

        return [
            'students' => $students,
            'employees' => (int) ($employees->total ?? 0),
            'teachers' => (int) ($employees->teachers ?? 0),
            'staff' => (int) ($employees->staff ?? 0),
            'attendance_total' => (int) ($attendance->total ?? 0),
            'present' => (int) ($attendance->present ?? 0),
            'permission' => (int) ($attendance->permission ?? 0),
            'absent' => (int) ($attendance->absent ?? 0),
            'sick' => (int) ($attendance->sick ?? 0),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function financeMetrics(?string $branchId, string $period, string $academicYearId): array
    {
        $payroll = DB::table('salaries')
            ->where('period', $period)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) AS paid_count, SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 'PAID' THEN net_salary ELSE 0 END) AS paid_amount, SUM(CASE WHEN status = 'PENDING' THEN net_salary ELSE 0 END) AS pending_amount")
            ->first();
        $spp = DB::table('spp_billings')
            ->where('academic_year_id', $academicYearId ?: null)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) AS paid_count, SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) AS unpaid_count, SUM(paid_amount) AS collected, SUM(GREATEST(amount - paid_amount, 0)) AS outstanding")
            ->first();

        return [
            'payroll_total' => (int) ($payroll->total ?? 0),
            'payroll_paid_count' => (int) ($payroll->paid_count ?? 0),
            'payroll_pending_count' => (int) ($payroll->pending_count ?? 0),
            'payroll_paid_amount' => (float) ($payroll->paid_amount ?? 0),
            'payroll_pending_amount' => (float) ($payroll->pending_amount ?? 0),
            'spp_total' => (int) ($spp->total ?? 0),
            'spp_paid_count' => (int) ($spp->paid_count ?? 0),
            'spp_unpaid_count' => (int) ($spp->unpaid_count ?? 0),
            'spp_collected' => (float) ($spp->collected ?? 0),
            'spp_outstanding' => (float) ($spp->outstanding ?? 0),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function branchSummaries(string $from, string $to, string $period, string $academicYearId)
    {
        $schoolCounts = DB::table('branches')
            ->leftJoin('students', 'students.branch_id', '=', 'branches.id')
            ->leftJoin('employees', 'employees.branch_id', '=', 'branches.id')
            ->select('branches.id')
            ->selectRaw('COUNT(DISTINCT students.id) AS students, COUNT(DISTINCT employees.id) AS employees')
            ->groupBy('branches.id')
            ->get()
            ->keyBy('id');
        $attendanceByBranch = DB::table('attendances')
            ->whereBetween('date', [$from, $to])
            ->select('branch_id')
            ->selectRaw("SUM(CASE WHEN status = 'HADIR' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN status IN ('IZIN', 'ALFA', 'SAKIT') THEN 1 ELSE 0 END) AS absent")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $payrollByBranch = DB::table('salaries')
            ->where('period', $period)
            ->select('branch_id')
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'PAID' THEN net_salary ELSE 0 END) AS paid, SUM(CASE WHEN status = 'PENDING' THEN net_salary ELSE 0 END) AS pending")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $sppByBranch = DB::table('spp_billings')
            ->when($academicYearId !== '', fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->select('branch_id')
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'PAID' THEN paid_amount ELSE 0 END) AS collected, SUM(GREATEST(amount - paid_amount, 0)) AS outstanding")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        return Branch::query()->orderBy('name')->get(['id', 'name', 'level'])->map(
            fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'level' => $branch->level,
                'students' => (int) ($schoolCounts->get($branch->id)->students ?? 0),
                'employees' => (int) ($schoolCounts->get($branch->id)->employees ?? 0),
                'present' => (int) ($attendanceByBranch->get($branch->id)->present ?? 0),
                'absent' => (int) ($attendanceByBranch->get($branch->id)->absent ?? 0),
                'payroll_total' => (int) ($payrollByBranch->get($branch->id)->total ?? 0),
                'payroll_paid' => (float) ($payrollByBranch->get($branch->id)->paid ?? 0),
                'payroll_pending' => (float) ($payrollByBranch->get($branch->id)->pending ?? 0),
                'spp_total' => (int) ($sppByBranch->get($branch->id)->total ?? 0),
                'spp_collected' => (float) ($sppByBranch->get($branch->id)->collected ?? 0),
                'spp_outstanding' => (float) ($sppByBranch->get($branch->id)->outstanding ?? 0),
            ]
        );
    }
}

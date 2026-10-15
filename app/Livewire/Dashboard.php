<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\Salary;
use App\Models\SppBilling;
use App\Models\Student;
use Livewire\Component;

class Dashboard extends Component
{
    public string $branchId = '';

    public function mount(): void
    {
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = (string) auth()->user()->branch_id;
        }
    }

    public function render()
    {
        $user = auth()->user();
        $canViewAllBranches = $user->role === 'admin_pusat';
        $branchId = $canViewAllBranches ? ($this->branchId ?: null) : $user->branch_id;
        $canManageSchool = in_array($user->role, ['admin_pusat', 'admin_cabang'], true);
        $canViewFinance = in_array($user->role, ['admin_pusat', 'keuangan'], true);
        $branches = $canViewAllBranches ? Branch::query()->orderBy('name')->get(['id', 'name', 'level']) : collect();
        $branch = $branchId ? Branch::query()->find($branchId, ['id', 'name', 'level']) : null;
        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first(['id', 'name']);
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $studentCount = Student::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->count();
        $employeeCount = Employee::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->count();
        $teacherCount = Employee::query()
            ->where('type', 'GURU')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();
        $attendanceCounts = Attendance::query()
            ->whereDate('date', $today)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("SUM(CASE WHEN status = 'HADIR' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status IN ('IZIN','ALFA','SAKIT') THEN 1 ELSE 0 END) as absent")
            ->first();
        $rosterCount = RosterSlot::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($activeAcademicYear, fn ($query) => $query->where('academic_year_id', $activeAcademicYear->id))
            ->count();

        $sppQuery = SppBilling::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($activeAcademicYear, fn ($query) => $query->where('academic_year_id', $activeAcademicYear->id));
        $sppSummary = (clone $sppQuery)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid, SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) as unpaid, SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as collected")
            ->first();
        $sppByMonth = (clone $sppQuery)
            ->selectRaw("month, SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as paid_amount, SUM(CASE WHEN status = 'UNPAID' THEN amount ELSE 0 END) as unpaid_amount")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $payrollPeriod = now()->format('Y-m');
        $payrollSummary = Salary::query()
            ->where('period', $payrollPeriod)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid, SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'PAID' THEN net_salary ELSE 0 END) as paid_amount, SUM(CASE WHEN status = 'PENDING' THEN net_salary ELSE 0 END) as pending_amount")
            ->first();

        $monthLabels = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $sppChart = collect(range(1, 12))->map(function (int $month) use ($sppByMonth, $monthLabels): array {
            $row = $sppByMonth->get($month);

            return [
                'label' => $monthLabels[$month],
                'paid' => (float) ($row->paid_amount ?? 0),
                'unpaid' => (float) ($row->unpaid_amount ?? 0),
            ];
        });
        $sppMax = max(1, (float) $sppChart->max(fn (array $month): float => $month['paid'] + $month['unpaid']));

        $trendStart = now()->startOfMonth()->subMonths(5);
        $payrollByPeriod = Salary::query()
            ->whereBetween('period', [$trendStart->format('Y-m'), now()->format('Y-m')])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('period, SUM(net_salary) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');
        $payrollChart = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $payrollByPeriod): array {
            $month = $trendStart->copy()->addMonths($offset);
            $period = $month->format('Y-m');

            return [
                'label' => $month->format('M'),
                'total' => (float) ($payrollByPeriod->get($period)->total ?? 0),
            ];
        });
        $payrollMax = max(1, (float) $payrollChart->max('total'));

        $recentAbsences = $canManageSchool
            ? Attendance::query()
                ->with('employee:id,name,nip')
                ->whereIn('status', ['IZIN', 'ALFA', 'SAKIT'])
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderByDesc('date')
                ->limit(6)
                ->get()
            : collect();

        $branchSummary = $canViewAllBranches && ! $branchId
            ? Branch::query()
                ->withCount(['students', 'employees'])
                ->orderBy('name')
                ->limit(8)
                ->get()
            : collect();

        return view('livewire.dashboard', compact(
            'user',
            'branch',
            'branches',
            'branchId',
            'canViewAllBranches',
            'canManageSchool',
            'canViewFinance',
            'activeAcademicYear',
            'studentCount',
            'employeeCount',
            'teacherCount',
            'attendanceCounts',
            'rosterCount',
            'sppSummary',
            'sppChart',
            'sppMax',
            'payrollSummary',
            'payrollChart',
            'payrollMax',
            'payrollPeriod',
            'recentAbsences',
            'branchSummary'
        ))->layout('layouts.app', ['title' => 'Dashboard']);
    }
}

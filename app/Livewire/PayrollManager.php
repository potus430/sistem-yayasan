<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\AcademicYear;
use App\Models\AnnualIncentiveAssignment;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchIncentive;
use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\RosterSlot;
use App\Models\Salary;
use App\Models\TeacherHourlyRate;
use App\Models\TeacherSubstitution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollManager extends Component
{
    use HasDateRangeFilter, WithPagination;

    public string $branchId = '';

    public string $employeeId = '';

    public string $period = '';

    public string $academicYearId = '';

    public string $allowances = '0';

    public int $perPage = 25;

    public int $slipsPerPage = 2;

    /** @var array<string, string> */
    public array $incentiveAmounts = [];

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->dateFilterMode = 'month';
        $this->dateFilterMonth = $this->period;
        $activeYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->academicYearId = $activeYearId;
        if (! in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true)) {
            $this->branchId = (string) auth()->user()->branch_id;
        }
    }

    public function updatedBranchId(): void
    {
        $this->employeeId = '';
        $this->incentiveAmounts = [];
        $this->allowances = '0';
        $this->resetPage();
    }

    public function updatedEmployeeId(): void
    {
        $this->loadSavedIncentiveAmounts();
        $this->loadSavedAllowances();
    }

    public function updatedPeriod(): void
    {
        if ($this->dateFilterMode === 'month') {
            $this->dateFilterMonth = $this->period;
        }
        $this->loadSavedIncentiveAmounts();
        $this->loadSavedAllowances();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function savePayroll(): void
    {
        abort_unless(auth()->user()->role === 'keuangan_pusat', 403);
        $user = auth()->user();
        $branchId = $this->effectiveBranchId($user);

        $validated = $this->validate([
            'employeeId' => ['required', 'uuid'],
            'period' => ['required', 'date_format:Y-m'],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'allowances' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'incentiveAmounts' => ['array'],
            'incentiveAmounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        abort_unless($branchId, 422, 'Pilih cabang sebelum memproses payroll.');
        $employee = Employee::query()
            ->where('branch_id', $branchId)
            ->findOrFail($validated['employeeId']);

        $definitions = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('calculation_type', 'MANUAL')
            ->orderBy('name')
            ->get();
        $definitionIds = $definitions->modelKeys();

        foreach (array_keys($validated['incentiveAmounts'] ?? []) as $submittedId) {
            if (! in_array($submittedId, $definitionIds, true)) {
                $this->addError('incentiveAmounts', 'Ada jenis insentif yang tidak valid untuk cabang ini. Muat ulang halaman dan coba kembali.');

                return;
            }
        }

        $calculation = $this->calculatePayroll(
            $branchId,
            $employee,
            $validated['period'],
            $validated['academicYearId'],
            $validated['incentiveAmounts'] ?? [],
            (float) $validated['allowances']
        );
        if ($employee->type === 'GURU' && ! $calculation['hourly_rate_configured']) {
            $this->addError('academicYearId', 'Tarif gaji guru per jam belum ditetapkan admin pusat untuk cabang dan tahun ajaran ini.');

            return;
        }
        if (! $calculation['academic_year_matches_period']) {
            $this->addError('academicYearId', 'Tahun ajaran yang dipilih tidak mencakup periode payroll.');

            return;
        }

        DB::transaction(function () use ($branchId, $employee, $validated, $calculation): void {
            Salary::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'period' => $validated['period']],
                [
                    'branch_id' => $branchId,
                    'base_salary' => $calculation['base_salary'],
                    'teaching_hours' => $calculation['teaching_hours'],
                    'hourly_rate' => $calculation['hourly_rate'],
                    'incentives' => $calculation['total_incentives'],
                    'incentive_details' => $calculation['incentive_details'],
                    'position_salary' => $calculation['position_salary'],
                    'position_details' => $calculation['position_details'],
                    'allowances' => $calculation['total_allowances'],
                    'allowance_details' => $calculation['allowance_details'],
                    'deductions' => $calculation['deductions'],
                    'net_salary' => $calculation['net_salary'],
                ]
            );
        });

        $this->dispatch('payroll-saved');
        session()->flash('success', 'Payroll berhasil dihitung dan disimpan.');
    }

    /**
     * @param  array<string, string|int|float>  $manualIncentiveAmounts
     * @return array{
     *     incentive_details: array<int, array<string, mixed>>,
     *     total_incentives: float,
     *     position_salary: float,
     *     position_details: array<int, array<string, mixed>>,
     *     allowance_details: array<int, array<string, mixed>>,
     *     total_allowances: float,
     *     deductions: float,
     *     net_salary: float,
     *     substitution_count: int,
     *     base_salary: float,
     *     teaching_hours: int,
     *     hourly_rate: float,
     *     hourly_rate_configured: bool,
     *     academic_year_matches_period: bool
     * }
     */
    private function calculatePayroll(
        string $branchId,
        Employee $employee,
        string $period,
        ?string $academicYearId,
        array $manualIncentiveAmounts,
        float $manualAllowance
    ): array {
        $teachingHours = 0;
        $hourlyRate = 0.0;
        $hourlyRateConfigured = false;
        $academicYearMatchesPeriod = true;
        $baseSalary = (float) $employee->base_salary;
        if ($employee->type === 'GURU' && $academicYearId) {
            $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            $periodStart = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $academicYearMatchesPeriod = $periodStart->lessThanOrEqualTo($academicYear->ends_on)
                && $periodEnd->greaterThanOrEqualTo($academicYear->starts_on);
            $hourlyRateValue = TeacherHourlyRate::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $academicYearId)
                ->value('amount');
            $hourlyRateConfigured = $hourlyRateValue !== null;
            $hourlyRate = (float) ($hourlyRateValue ?? 0);
            $teachingHours = $this->monthlyRosterHours($branchId, $employee->id, $period, $academicYearId);
            $baseSalary = round($teachingHours * $hourlyRate, 2);
        }
        $definitions = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('calculation_type', 'MANUAL')
            ->orderBy('name')
            ->get();
        $manualDetails = $definitions->map(fn (BranchIncentive $definition): array => [
            'branch_incentive_id' => $definition->id,
            'name' => $definition->name,
            'amount' => round((float) ($manualIncentiveAmounts[$definition->id] ?? 0), 2),
            'type' => 'MANUAL',
        ])->all();

        $annualDetails = $academicYearId
            ? AnnualIncentiveAssignment::query()
                ->with('incentive:id,name')
                ->where('branch_id', $branchId)
                ->where('employee_id', $employee->id)
                ->where('academic_year_id', $academicYearId)
                ->whereHas('incentive', fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->where('calculation_type', 'ANNUAL'))
                ->get()
                ->map(fn (AnnualIncentiveAssignment $assignment): array => [
                    'branch_incentive_id' => $assignment->branch_incentive_id,
                    'name' => $assignment->incentive->name,
                    'amount' => (float) $assignment->amount,
                    'type' => 'ANNUAL',
                ])->all()
            : [];
        $positionAssignments = $academicYearId
            ? EmployeePositionAssignment::query()
                ->with('position:id,name,monthly_salary_addition,monthly_allowance,monthly_incentive')
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $academicYearId)
                ->where('employee_id', $employee->id)
                ->whereHas('position', fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('is_active', true))
                ->get()
            : collect();
        $positionDetails = $positionAssignments
            ->map(fn (EmployeePositionAssignment $assignment): array => [
                'position_id' => $assignment->branch_position_id,
                'name' => $assignment->position->name,
                'class_name' => $assignment->class_name,
                'salary_addition' => (float) $assignment->position->monthly_salary_addition,
                'allowance' => (float) $assignment->position->monthly_allowance,
                'incentive' => (float) $assignment->position->monthly_incentive,
            ])->all();
        $positionSalary = round(array_sum(array_column($positionDetails, 'salary_addition')), 2);
        $positionIncentiveDetails = array_map(fn (array $detail): array => [
            'branch_incentive_id' => null,
            'position_id' => $detail['position_id'],
            'name' => 'Insentif jabatan · '.$detail['name'],
            'amount' => $detail['incentive'],
            'type' => 'POSITION',
        ], array_filter($positionDetails, fn (array $detail): bool => $detail['incentive'] > 0));
        $incentiveDetails = array_merge($annualDetails, $manualDetails, $positionIncentiveDetails);
        $totalIncentives = round(array_sum(array_column($incentiveDetails, 'amount')), 2);

        $month = Carbon::createFromFormat('Y-m', $period);
        $branch = Branch::query()->findOrFail($branchId);
        $transportAllowance = (float) $branch->monthly_transport_allowance;
        $substitutionCount = $employee->type === 'GURU'
            ? TeacherSubstitution::query()
                ->where('branch_id', $branchId)
                ->where('substitute_employee_id', $employee->id)
                ->where('is_cancelled', false)
                ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->count()
            : 0;
        $substitutionAllowance = round($substitutionCount * (float) $branch->substitution_allowance_per_period, 2);
        $allowanceDetails = [
            ['name' => 'Transport bulanan', 'type' => 'TRANSPORT', 'amount' => round($transportAllowance, 2)],
            ['name' => 'Invalen guru pengganti', 'type' => 'SUBSTITUTION', 'count' => $substitutionCount, 'rate' => (float) $branch->substitution_allowance_per_period, 'amount' => $substitutionAllowance],
            ['name' => 'Tunjangan tambahan manual', 'type' => 'MANUAL', 'amount' => round($manualAllowance, 2)],
        ];
        foreach ($positionDetails as $positionDetail) {
            if ($positionDetail['allowance'] > 0) {
                $allowanceDetails[] = [
                    'name' => 'Tunjangan jabatan · '.$positionDetail['name'].($positionDetail['class_name'] ? ' · '.$positionDetail['class_name'] : ''),
                    'type' => 'POSITION',
                    'position_id' => $positionDetail['position_id'],
                    'amount' => $positionDetail['allowance'],
                ];
            }
        }
        $totalAllowances = round($transportAllowance + $substitutionAllowance + $manualAllowance, 2);
        $totalAllowances += round(array_sum(array_column($positionDetails, 'allowance')), 2);

        $absentDays = Attendance::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['ALFA', 'IZIN'])
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->count();
        $deductions = round(($baseSalary / $month->daysInMonth) * $absentDays, 2);
        $netSalary = max(0, round($baseSalary + $positionSalary + $totalIncentives + $totalAllowances - $deductions, 2));

        return [
            'incentive_details' => $incentiveDetails,
            'total_incentives' => $totalIncentives,
            'position_salary' => $positionSalary,
            'position_details' => $positionDetails,
            'allowance_details' => $allowanceDetails,
            'total_allowances' => $totalAllowances,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'substitution_count' => $substitutionCount,
            'base_salary' => $baseSalary,
            'teaching_hours' => $teachingHours,
            'hourly_rate' => $hourlyRate,
            'hourly_rate_configured' => $hourlyRateConfigured,
            'academic_year_matches_period' => $academicYearMatchesPeriod,
        ];
    }

    private function monthlyRosterHours(string $branchId, string $employeeId, string $period, string $academicYearId): int
    {
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $monthStart = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $start = $monthStart->greaterThan($academicYear->starts_on) ? $monthStart : $academicYear->starts_on->copy();
        $end = $monthEnd->lessThan($academicYear->ends_on) ? $monthEnd : $academicYear->ends_on->copy();
        if ($start->greaterThan($end)) {
            return 0;
        }

        $weeklyHours = RosterSlot::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->where('employee_id', $employeeId)
            ->selectRaw('weekday, COUNT(*) as hours')
            ->groupBy('weekday')
            ->pluck('hours', 'weekday');
        if ($weeklyHours->isEmpty()) {
            return 0;
        }

        $monthlyHours = 0;
        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $monthlyHours += (int) ($weeklyHours[$date->dayOfWeekIso] ?? 0);
        }

        return $monthlyHours;
    }

    public function markPaid(string $salaryId): void
    {
        abort_unless(auth()->user()->role === 'keuangan_pusat', 403);
        $query = Salary::query()->whereKey($salaryId);
        $query->firstOrFail()->update(['status' => 'PAID']);
        session()->flash('success', 'Status gaji diperbarui menjadi lunas.');
    }

    private function effectiveBranchId($user): ?string
    {
        return in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? ($this->branchId ?: null) : $user->branch_id;
    }

    private function loadSavedAllowances(): void
    {
        $branchId = $this->effectiveBranchId(auth()->user());
        if (! $branchId || ! $this->employeeId || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->allowances = '0';

            return;
        }

        $salary = Salary::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $this->employeeId)
            ->where('period', $this->period)
            ->first();

        if (! $salary) {
            $this->allowances = '0';

            return;
        }

        $manualAllowance = collect($salary->allowance_details ?? [])
            ->firstWhere('type', 'MANUAL')['amount'] ?? null;
        $this->allowances = (string) ($manualAllowance ?? ($salary->allowance_details ? 0 : $salary->allowances));
    }

    private function loadSavedIncentiveAmounts(): void
    {
        $branchId = $this->effectiveBranchId(auth()->user());
        if (! $branchId || ! $this->employeeId || ! $this->period) {
            return;
        }

        $this->incentiveAmounts = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('calculation_type', 'MANUAL')
            ->pluck('default_amount', 'id')
            ->map(fn ($amount): string => (string) $amount)
            ->all();

        $salary = Salary::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $this->employeeId)
            ->where('period', $this->period)
            ->first();

        if (! $salary) {
            return;
        }

        foreach ($salary->incentive_details ?? [] as $detail) {
            if (isset($detail['branch_incentive_id'])) {
                $this->incentiveAmounts[$detail['branch_incentive_id']] = (string) ($detail['amount'] ?? '0');
            }
        }
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $this->effectiveBranchId($user);
        $branches = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? Branch::query()->orderBy('name')->get() : collect();
        $employees = $branchId
            ? Employee::query()->where('branch_id', $branchId)->orderBy('name')->get()
            : collect();
        $manualIncentiveTypes = $branchId
            ? BranchIncentive::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->where('calculation_type', 'MANUAL')
                ->orderBy('name')
                ->get()
            : collect();
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get();

        $salaryQuery = Salary::query()
            ->with('employee:id,name,nip')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        if ($this->dateFilterMode !== 'all') {
            [$from, $to] = $this->dateFilterBounds();
            $salaryQuery
                ->when($from, fn ($query, string $date) => $query->where('period', '>=', substr($date, 0, 7)))
                ->when($to, fn ($query, string $date) => $query->where('period', '<=', substr($date, 0, 7)));
        }
        $salaries = $salaryQuery->orderByDesc('updated_at')->paginate($this->perPage);
        $previewEmployee = $branchId && $this->employeeId
            ? Employee::query()->where('branch_id', $branchId)->find($this->employeeId)
            : null;
        $payrollPreview = $previewEmployee && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)
            ? $this->calculatePayroll(
                $branchId,
                $previewEmployee,
                $this->period,
                $this->academicYearId && AcademicYear::query()->whereKey($this->academicYearId)->exists() ? $this->academicYearId : null,
                $this->incentiveAmounts,
                max(0, (float) $this->allowances)
            )
            : null;

        return view('livewire.payroll-manager', compact(
            'branches',
            'employees',
            'manualIncentiveTypes',
            'academicYears',
            'salaries',
            'payrollPreview',
            'previewEmployee'
        ))
            ->layout('layouts.app', ['title' => 'Manajemen Penggajian']);
    }
}

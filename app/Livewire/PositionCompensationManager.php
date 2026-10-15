<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\BranchPosition;
use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PositionCompensationManager extends Component
{
    public string $branchId = '';

    public string $academicYearId = '';

    public string $newPositionName = '';

    public bool $newPositionRequiresClass = false;

    public string $newMonthlySalaryAddition = '0';

    public string $newMonthlyAllowance = '0';

    public string $newMonthlyIncentive = '0';

    public string $positionId = '';

    public string $employeeId = '';

    public string $className = '';

    /** @var array<string, array{name: string, requires_class: bool, monthly_salary_addition: string, monthly_allowance: string, monthly_incentive: string}> */
    public array $positionInputs = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->branchId = $user->role === 'admin_pusat' ? '' : (string) $user->branch_id;
        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->ensureDefaultPositions();
        $this->loadPositionInputs();
    }

    public function updatedBranchId(): void
    {
        $this->resetAssignmentForm();
        $this->ensureDefaultPositions();
        $this->loadPositionInputs();
    }

    public function updatedAcademicYearId(): void
    {
        $activeYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        if ($this->academicYearId !== $activeYearId) {
            $this->academicYearId = $activeYearId;
            $this->addError('academicYearId', 'Penugasan hanya dapat dikelola pada tahun ajaran yang sedang berjalan.');
        }

        $this->resetAssignmentForm();
        $this->ensureDefaultPositions();
        $this->loadPositionInputs();
    }

    public function updatedPositionId(): void
    {
        $this->className = '';
    }

    public function addPosition(): void
    {
        $this->authorizeCompensationManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang terlebih dahulu.');
        $academicYearId = $this->requireActiveAcademicYearId();

        $validated = $this->validate([
            'newPositionName' => ['required', 'string', 'max:100'],
            'newPositionRequiresClass' => ['boolean'],
            'newMonthlySalaryAddition' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'newMonthlyAllowance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'newMonthlyIncentive' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $name = trim($validated['newPositionName']);
        if (BranchPosition::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $this->academicYearId)
            ->where('name', $name)
            ->exists()) {
            $this->addError('newPositionName', 'Nama jabatan sudah digunakan di cabang dan tahun ajaran ini.');

            return;
        }

        BranchPosition::query()->create([
            'branch_id' => $branchId,
            'academic_year_id' => $academicYearId,
            'name' => $name,
            'requires_class' => $validated['newPositionRequiresClass'],
            'monthly_salary_addition' => $validated['newMonthlySalaryAddition'],
            'monthly_allowance' => $validated['newMonthlyAllowance'],
            'monthly_incentive' => $validated['newMonthlyIncentive'],
        ]);

        $this->reset(['newPositionName', 'newPositionRequiresClass']);
        $this->newMonthlySalaryAddition = '0';
        $this->newMonthlyAllowance = '0';
        $this->newMonthlyIncentive = '0';
        $this->loadPositionInputs();
        session()->flash('success', 'Jabatan dan komponen kompensasi berhasil ditambahkan.');
    }

    public function savePosition(string $positionId): void
    {
        $this->authorizeCompensationManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang terlebih dahulu.');
        $academicYearId = $this->requireActiveAcademicYearId();

        $validated = $this->validate([
            "positionInputs.{$positionId}.name" => ['required', 'string', 'max:100'],
            "positionInputs.{$positionId}.requires_class" => ['boolean'],
            "positionInputs.{$positionId}.monthly_salary_addition" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            "positionInputs.{$positionId}.monthly_allowance" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            "positionInputs.{$positionId}.monthly_incentive" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        $values = $validated['positionInputs'][$positionId];
        $name = trim($values['name']);

        $position = BranchPosition::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->whereKey($positionId)
            ->firstOrFail();
        if ($position->requires_class !== (bool) $values['requires_class'] && $position->assignments()->exists()) {
            $this->addError("positionInputs.{$positionId}.requires_class", 'Hapus atau pindahkan penugasan terlebih dahulu sebelum mengubah kebutuhan kelas.');

            return;
        }

        if (BranchPosition::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->where('name', $name)
            ->where('id', '<>', $positionId)
            ->exists()) {
            $this->addError("positionInputs.{$positionId}.name", 'Nama jabatan sudah digunakan pada tahun ajaran ini.');

            return;
        }

        $position->update([
            'name' => $name,
            'requires_class' => $values['requires_class'],
            'monthly_salary_addition' => $values['monthly_salary_addition'],
            'monthly_allowance' => $values['monthly_allowance'],
            'monthly_incentive' => $values['monthly_incentive'],
        ]);

        $this->loadPositionInputs();
        session()->flash('success', 'Pengaturan jabatan berhasil disimpan.');
    }

    public function togglePosition(string $positionId): void
    {
        $this->authorizeCompensationManagement();
        $academicYearId = $this->requireActiveAcademicYearId();
        $position = BranchPosition::query()
            ->where('branch_id', $this->effectiveBranchId())
            ->where('academic_year_id', $academicYearId)
            ->whereKey($positionId)
            ->firstOrFail();
        $position->update(['is_active' => ! $position->is_active]);
        session()->flash('success', $position->is_active
            ? 'Jabatan diaktifkan kembali.'
            : 'Jabatan dinonaktifkan; payroll lama tetap menyimpan snapshot kompensasinya.');
    }

    public function assignPosition(): void
    {
        $this->resetValidation();
        $this->authorizeAssignmentManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang terlebih dahulu.');
        $academicYearId = $this->requireActiveAcademicYearId();

        $position = BranchPosition::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->findOrFail($this->positionId);
        $validated = $this->validate([
            'positionId' => ['required', 'uuid'],
            'employeeId' => ['required', 'uuid'],
            'className' => [
                Rule::requiredIf($position->requires_class),
                'nullable',
                'string',
                'max:50',
                Rule::exists('school_classes', 'name')->where('branch_id', $branchId),
            ],
        ]);
        $employee = Employee::query()
            ->where('branch_id', $branchId)
            ->findOrFail($validated['employeeId']);

        if ($position->requires_class && $employee->type !== 'GURU') {
            $this->addError('employeeId', 'Wali kelas hanya dapat ditetapkan kepada pegawai bertipe guru.');

            return;
        }

        $className = $position->requires_class ? $validated['className'] : null;

        DB::transaction(function () use ($position, $employee, $branchId, $academicYearId, $className): void {
            Employee::query()
                ->where('branch_id', $branchId)
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();
            BranchPosition::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $academicYearId)
                ->whereKey($position->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $assignmentQuery = EmployeePositionAssignment::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $academicYearId);

            $existingEmployeeAssignment = (clone $assignmentQuery)
                ->where('employee_id', $employee->id)
                ->where(function ($query) use ($position, $className, $employee): void {
                    if ($position->requires_class) {
                        $query->where(function ($classQuery) use ($className): void {
                            $classQuery->whereNotNull('class_name')
                                ->where('class_name', '<>', $className);
                        });
                        if ($employee->type !== 'GURU') {
                            $query->orWhereHas('position', fn ($positionQuery) => $positionQuery->where('requires_class', false));
                        }
                    } else {
                        $query->whereHas('position', fn ($positionQuery) => $positionQuery->where('requires_class', false))
                            ->where('branch_position_id', '<>', $position->id);
                    }
                })
                ->lockForUpdate()
                ->exists();

            if ($existingEmployeeAssignment) {
                $this->addError(
                    'employeeId',
                    $position->requires_class
                        ? 'Guru hanya dapat menjadi wali kelas untuk satu kelas. Staff tidak dapat ditetapkan sebagai wali kelas.'
                        : 'Setiap guru atau staff hanya dapat memegang satu jabatan non-kelas pada tahun ajaran berjalan.'
                );

                return;
            }

            $assignmentForPosition = (clone $assignmentQuery)
                ->where('branch_position_id', $position->id);

            if ($position->requires_class) {
                $classAlreadyAssigned = (clone $assignmentQuery)
                    ->where('class_name', $className)
                    ->where('employee_id', '<>', $employee->id)
                    ->whereHas('position', fn ($positionQuery) => $positionQuery->where('requires_class', true))
                    ->lockForUpdate()
                    ->exists();
                if ($classAlreadyAssigned) {
                    $this->addError('className', 'Kelas ini sudah memiliki wali kelas untuk tahun ajaran tersebut.');

                    return;
                }
            } else {
                $positionAlreadyAssigned = (clone $assignmentForPosition)
                    ->where('employee_id', '<>', $employee->id)
                    ->lockForUpdate()
                    ->exists();
                if ($positionAlreadyAssigned) {
                    $this->addError('positionId', 'Jabatan ini sudah memiliki pegawai untuk tahun ajaran tersebut.');

                    return;
                }
            }

            EmployeePositionAssignment::query()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'academic_year_id' => $academicYearId,
                    'employee_id' => $employee->id,
                    'branch_position_id' => $position->id,
                ],
                ['class_name' => $className]
            );
        });

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->className = '';
        session()->flash('success', 'Penugasan jabatan pegawai berhasil disimpan.');
    }

    public function removeAssignment(string $assignmentId): void
    {
        $this->authorizeAssignmentManagement();
        $academicYearId = $this->requireActiveAcademicYearId();
        EmployeePositionAssignment::query()
            ->where('branch_id', $this->effectiveBranchId())
            ->where('academic_year_id', $academicYearId)
            ->whereKey($assignmentId)
            ->firstOrFail()
            ->delete();

        session()->flash('success', 'Penugasan jabatan berhasil dihapus.');
    }

    private function authorizeCompensationManagement(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'keuangan'], true), 403);
    }

    private function authorizeAssignmentManagement(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true), 403);
    }

    private function effectiveBranchId(): ?string
    {
        $user = auth()->user();

        return $user->role === 'admin_pusat' ? ($this->branchId ?: null) : ($user->branch_id ?: null);
    }

    private function requireActiveAcademicYearId(): string
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        abort_unless(
            $activeYearId && $this->academicYearId === $activeYearId,
            422,
            'Penugasan hanya dapat dikelola pada tahun ajaran yang sedang berjalan.'
        );

        return $activeYearId;
    }

    private function resetAssignmentForm(): void
    {
        $this->positionId = '';
        $this->employeeId = '';
        $this->className = '';
    }

    private function ensureDefaultPositions(): void
    {
        $branchId = $this->effectiveBranchId();
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        if (! $branchId || ! $activeYearId || $this->academicYearId !== $activeYearId) {
            return;
        }

        foreach ([
            ['Kepala Sekolah', false],
            ['Wakil Kepala Sekolah', false],
            ['Wali Kelas', true],
        ] as [$name, $requiresClass]) {
            BranchPosition::query()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'academic_year_id' => $activeYearId,
                    'name' => $name,
                ],
                [
                    'requires_class' => $requiresClass,
                    'monthly_salary_addition' => 0,
                    'monthly_allowance' => 0,
                    'monthly_incentive' => 0,
                    'is_active' => true,
                ]
            );
        }
    }

    private function loadPositionInputs(): void
    {
        $branchId = $this->effectiveBranchId();
        $this->positionInputs = $branchId && $this->academicYearId
            ? BranchPosition::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $this->academicYearId)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (BranchPosition $position): array => [
                    $position->id => [
                        'name' => $position->name,
                        'requires_class' => $position->requires_class,
                        'monthly_salary_addition' => (string) $position->monthly_salary_addition,
                        'monthly_allowance' => (string) $position->monthly_allowance,
                        'monthly_incentive' => (string) $position->monthly_incentive,
                    ],
                ])->all()
            : [];
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $this->effectiveBranchId();
        $branches = $user->role === 'admin_pusat' ? Branch::query()->orderBy('name')->get() : collect();
        $academicYears = AcademicYear::query()->where('is_active', true)->orderByDesc('starts_on')->get();
        $activeYearId = $academicYears->first()?->id;
        $positions = $branchId && $activeYearId && $this->academicYearId === $activeYearId
            ? BranchPosition::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $activeYearId)
                ->withCount('assignments')
                ->orderBy('name')
                ->get()
            : collect();
        $employees = $branchId
            ? Employee::query()->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'nip', 'type'])
            : collect();
        $classes = $branchId
            ? DB::table('school_classes')->where('branch_id', $branchId)->orderBy('name')->pluck('name')
            : collect();
        $assignments = $branchId && $activeYearId && $this->academicYearId === $activeYearId
            ? EmployeePositionAssignment::query()
                ->with(['employee:id,name,nip,type', 'position:id,name,monthly_salary_addition,monthly_allowance,monthly_incentive'])
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $activeYearId)
                ->orderBy('class_name')
                ->orderBy('branch_position_id')
                ->get()
            : collect();

        return view('livewire.position-compensation-manager', compact(
            'branches',
            'academicYears',
            'positions',
            'employees',
            'classes',
            'assignments'
        ))->layout('layouts.app', ['title' => 'Jabatan & Kompensasi']);
    }
}

<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeDirectory extends Component
{
    use HasDateRangeFilter, WithPagination;

    public string $branchId = '';

    public string $search = '';

    public string $type = '';

    public string $status = '';

    public int $perPage = 25;

    public bool $showForm = false;

    public string $editingId = '';

    public string $formBranchId = '';

    public string $formNip = '';

    public string $formName = '';

    public string $formType = 'GURU';

    public string $formBaseSalary = '0';

    public string $formStatus = 'TETAP';

    /** @var array<string, string> */
    public array $salaryAmounts = [];

    public function mount(): void
    {
        if (! in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true)) {
            $this->branchId = (string) auth()->user()->branch_id;
        }

        $this->loadSalaryAmounts();
    }

    public function updated($property): void
    {
        if (in_array($property, ['branchId', 'search', 'type', 'status'], true)) {
            $this->resetPage();
        }

        if ($property === 'branchId') {
            $this->loadSalaryAmounts();
        }
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function createEmployee(): void
    {
        $this->authorizeManagement();
        $this->resetForm();
        $user = auth()->user();
        $this->formBranchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        if ($user->role === 'admin_pusat') {
            $this->formType = 'STAFF';
        }
        $this->showForm = true;
    }

    public function editEmployee(string $employeeId): void
    {
        $this->authorizeManagement();
        $employee = $this->scopedEmployees()->findOrFail($employeeId);
        abort_if(auth()->user()->role === 'admin_pusat' && $employee->type !== 'STAFF', 403, 'Admin pusat hanya dapat mengelola profil staff; data guru dikelola admin cabang.');
        $this->editingId = $employee->id;
        $this->formBranchId = $employee->branch_id;
        $this->formNip = $employee->nip;
        $this->formName = $employee->name;
        $this->formType = $employee->type;
        $this->formBaseSalary = (string) $employee->base_salary;
        $this->formStatus = $employee->status;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveEmployee(): void
    {
        $this->authorizeManagement();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->formBranchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum menyimpan pegawai.');

        $validated = $this->validate([
            'formBranchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'formNip' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'nip')->where('branch_id', $branchId)->ignore($this->editingId ?: null),
            ],
            'formName' => ['required', 'string', 'max:255'],
            'formType' => ['required', Rule::in($user->role === 'admin_pusat' ? ['STAFF'] : ['GURU', 'STAFF'])],
            'formBaseSalary' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'formStatus' => ['required', Rule::in(['TETAP', 'HONORER'])],
        ]);

        $employee = $this->editingId ? $this->scopedEmployees()->findOrFail($this->editingId) : new Employee;
        if ($employee->exists && $user->role === 'admin_pusat' && $employee->type !== 'STAFF') {
            abort(403, 'Admin pusat hanya dapat mengelola profil staff; data guru dikelola admin cabang.');
        }

        if ($employee->exists && $employee->type !== $validated['formType'] && $this->employeeHasHistory($employee)) {
            $this->addError('formType', 'Jenis pegawai tidak dapat diubah karena pegawai sudah memiliki data absensi, payroll, roster, atau insentif.');

            return;
        }
        if ($employee->exists && $employee->branch_id !== $branchId && $this->employeeHasHistory($employee)) {
            $this->addError('formBranchId', 'Cabang pegawai tidak dapat dipindahkan karena sudah memiliki data absensi, payroll, roster, atau insentif.');

            return;
        }

        $attributes = [
            'branch_id' => $branchId,
            'nip' => trim($validated['formNip']),
            'name' => trim($validated['formName']),
            'type' => $user->role === 'admin_pusat' ? 'STAFF' : $validated['formType'],
            'status' => $validated['formStatus'],
        ];
        if (! $employee->exists) {
            $attributes['base_salary'] = 0;
        }
        $employee->fill($attributes)->save();

        $this->showForm = false;
        $this->resetForm();
        $this->loadSalaryAmounts();
        session()->flash('success', 'Data pegawai berhasil disimpan.');
    }

    public function deleteEmployee(string $employeeId): void
    {
        $this->authorizeManagement();
        $employee = $this->scopedEmployees()->findOrFail($employeeId);
        abort_if(auth()->user()->role === 'admin_pusat' && $employee->type !== 'STAFF', 403, 'Data guru dikelola admin cabang.');

        if ($this->employeeHasHistory($employee)) {
            session()->flash('error', 'Pegawai tidak dapat dihapus karena masih memiliki riwayat absensi, payroll, roster, insentif, atau penugasan pengganti.');

            return;
        }

        $employee->delete();
        session()->flash('success', 'Data pegawai berhasil dihapus.');
    }

    private function scopedEmployees(): Builder
    {
        $user = auth()->user();

        return Employee::query()->when(
            ! in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true),
            fn (Builder $query) => $query->where('branch_id', $user->branch_id)
        );
    }

    private function employeeHasHistory(Employee $employee): bool
    {
        return DB::table('attendances')->where('employee_id', $employee->id)->exists()
            || DB::table('salaries')->where('employee_id', $employee->id)->exists()
            || DB::table('roster_slots')->where('employee_id', $employee->id)->exists()
            || DB::table('annual_incentive_assignments')->where('employee_id', $employee->id)->exists()
            || DB::table('teacher_substitutions')->where(fn ($query) => $query
                ->where('absent_employee_id', $employee->id)
                ->orWhere('substitute_employee_id', $employee->id))->exists();
    }

    public function saveBaseSalary(string $employeeId): void
    {
        abort_unless(auth()->user()->role === 'keuangan_pusat', 403);
        $employee = Employee::query()->findOrFail($employeeId);
        abort_if($employee->type !== 'STAFF', 403, 'Gaji pokok guru dihitung dari tarif per jam dan roster mengajar.');
        $validated = $this->validate([
            "salaryAmounts.{$employeeId}" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $employee->update(['base_salary' => $validated['salaryAmounts'][$employeeId]]);
        $this->salaryAmounts[$employeeId] = (string) $employee->fresh()->base_salary;
        session()->flash('success', 'Nilai gaji pokok berhasil diperbarui.');
    }

    private function authorizeManagement(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true), 403);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'formBranchId', 'formNip', 'formName']);
        $this->formType = 'GURU';
        $this->formBaseSalary = '0';
        $this->formStatus = 'TETAP';
        $this->resetValidation();
    }

    private function loadSalaryAmounts(): void
    {
        if (auth()->user()->role !== 'keuangan_pusat') {
            $this->salaryAmounts = [];

            return;
        }

        $branchId = $this->branchId ?: null;
        $this->salaryAmounts = Employee::query()
            ->where('type', 'STAFF')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->pluck('base_salary', 'id')
            ->map(fn ($amount): string => (string) $amount)
            ->all();
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? ($this->branchId ?: null) : $user->branch_id;
        $query = Employee::query()
            ->with('branch:id,name,level')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query, 'created_at'))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', $term)
                    ->orWhere('nip', 'like', $term));
            });

        $totalEmployees = (clone $query)->count();
        $teachersCount = (clone $query)->where('type', 'GURU')->count();
        $staffCount = (clone $query)->where('type', 'STAFF')->count();
        $permanentCount = (clone $query)->where('status', 'TETAP')->count();
        $employees = $query->orderBy('branch_id')->orderBy('type')->orderBy('name')->paginate($this->perPage);
        $branches = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? Branch::query()->orderBy('name')->get(['id', 'name', 'level']) : collect();

        return view('livewire.employee-directory', compact('branches', 'employees', 'totalEmployees', 'teachersCount', 'staffCount', 'permanentCount'))
            ->layout('layouts.app', ['title' => 'Data pegawai dan guru']);
    }
}

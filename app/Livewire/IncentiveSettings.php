<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\AnnualIncentiveAssignment;
use App\Models\Branch;
use App\Models\BranchIncentive;
use App\Models\Employee;
use App\Models\TeacherHourlyRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class IncentiveSettings extends Component
{
    public string $branchId = '';

    public string $monthlyTransportAllowance = '0';

    public string $substitutionAllowancePerPeriod = '0';

    public string $teacherHourlyRate = '0';

    public string $newIncentiveName = '';

    public string $newIncentiveDefault = '0';

    public string $newIncentiveType = 'MANUAL';

    public string $employeeId = '';

    public string $academicYearId = '';

    /** @var array<string, string> */
    public array $annualIncentiveAmounts = [];

    /** @var array<string, array{name: string, calculation_type: string, default_amount: string}> */
    public array $incentiveDefinitions = [];

    public function mount(): void
    {
        $this->authorizePageAccess();
        $user = auth()->user();
        if (! in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true)) {
            $this->branchId = (string) $user->branch_id;
        }

        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->loadBranchSettings();
        $this->loadTeacherHourlyRate();
        $this->loadIncentiveDefinitions();
        $this->loadAnnualIncentiveAmounts();
    }

    public function updatedBranchId(): void
    {
        $this->employeeId = '';
        $this->annualIncentiveAmounts = [];
        $this->loadBranchSettings();
        $this->loadTeacherHourlyRate();
        $this->loadIncentiveDefinitions();
    }

    public function updatedEmployeeId(): void
    {
        $this->loadAnnualIncentiveAmounts();
    }

    public function updatedAcademicYearId(): void
    {
        $this->loadTeacherHourlyRate();
        $this->loadAnnualIncentiveAmounts();
    }

    public function saveTeacherHourlyRate(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengatur tarif guru.');

        $validated = $this->validate([
            'branchId' => ['required', 'uuid', Rule::exists('branches', 'id')],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'teacherHourlyRate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        TeacherHourlyRate::query()->updateOrCreate(
            ['branch_id' => $branchId, 'academic_year_id' => $validated['academicYearId']],
            ['amount' => $validated['teacherHourlyRate']]
        );

        session()->flash('success', 'Tarif gaji guru per jam berhasil disimpan.');
    }

    public function saveBranchPayrollSettings(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengatur tunjangan payroll.');

        $validated = $this->validate([
            'monthlyTransportAllowance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'substitutionAllowancePerPeriod' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'branchId' => [auth()->user()->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
        ]);

        Branch::query()->findOrFail($branchId)->update([
            'monthly_transport_allowance' => $validated['monthlyTransportAllowance'],
            'substitution_allowance_per_period' => $validated['substitutionAllowancePerPeriod'],
        ]);

        session()->flash('success', 'Pengaturan tunjangan cabang berhasil disimpan.');
    }

    public function addIncentiveType(): void
    {
        $this->authorizeIncentiveDefinitionManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum menambahkan jenis insentif.');

        $validated = $this->validate([
            'newIncentiveName' => ['required', 'string', 'max:100'],
            'newIncentiveDefault' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'newIncentiveType' => ['required', 'in:ANNUAL,MANUAL'],
        ]);
        $name = trim($validated['newIncentiveName']);

        if (BranchIncentive::query()->where('branch_id', $branchId)->where('name', $name)->exists()) {
            $this->addError('newIncentiveName', 'Nama insentif tersebut sudah ada pada cabang ini.');

            return;
        }

        BranchIncentive::query()->create([
            'branch_id' => $branchId,
            'name' => $name,
            'calculation_type' => $validated['newIncentiveType'],
            'default_amount' => $validated['newIncentiveDefault'],
        ]);

        $this->reset('newIncentiveName');
        $this->newIncentiveDefault = '0';
        $this->newIncentiveType = 'MANUAL';
        $this->loadIncentiveDefinitions();
        session()->flash('success', 'Jenis insentif cabang berhasil ditambahkan.');
    }

    public function saveIncentiveType(string $incentiveId): void
    {
        $this->authorizeIncentiveDefinitionManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengubah jenis insentif.');

        $validated = $this->validate([
            "incentiveDefinitions.{$incentiveId}.name" => ['required', 'string', 'max:100'],
            "incentiveDefinitions.{$incentiveId}.calculation_type" => ['required', 'in:ANNUAL,MANUAL'],
            "incentiveDefinitions.{$incentiveId}.default_amount" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        $values = $validated['incentiveDefinitions'][$incentiveId];
        $name = trim($values['name']);

        $duplicateExists = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('name', $name)
            ->where('id', '<>', $incentiveId)
            ->exists();
        if ($duplicateExists) {
            $this->addError("incentiveDefinitions.{$incentiveId}.name", 'Nama insentif tersebut sudah digunakan pada cabang ini.');

            return;
        }

        BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->whereKey($incentiveId)
            ->firstOrFail()
            ->update([
                'name' => $name,
                'calculation_type' => $values['calculation_type'],
                'default_amount' => $values['default_amount'],
            ]);

        $this->loadIncentiveDefinitions();
        session()->flash('success', 'Jenis insentif berhasil diperbarui.');
    }

    public function toggleIncentiveType(string $incentiveId): void
    {
        $this->authorizeIncentiveDefinitionManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengubah jenis insentif.');

        $incentive = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->whereKey($incentiveId)
            ->firstOrFail();
        $incentive->update(['is_active' => ! $incentive->is_active]);

        $this->loadIncentiveDefinitions();
        $this->loadAnnualIncentiveAmounts();
        session()->flash('success', $incentive->is_active
            ? 'Jenis insentif diaktifkan kembali.'
            : 'Jenis insentif dinonaktifkan; data payroll yang sudah tersimpan tidak berubah.');
    }

    public function saveAnnualIncentives(): void
    {
        $this->authorizeSettingsManagement();
        $branchId = $this->effectiveBranchId();
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengatur insentif tahunan.');

        $validated = $this->validate([
            'employeeId' => ['required', 'uuid'],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'annualIncentiveAmounts' => ['array'],
            'annualIncentiveAmounts.*' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        Employee::query()->where('branch_id', $branchId)->findOrFail($validated['employeeId']);
        $annualTypes = BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('calculation_type', 'ANNUAL')
            ->get();

        foreach (array_keys($validated['annualIncentiveAmounts']) as $submittedId) {
            abort_unless($annualTypes->contains('id', $submittedId), 422, 'Jenis insentif tahunan tidak valid untuk cabang ini.');
        }

        DB::transaction(function () use ($annualTypes, $validated, $branchId): void {
            foreach ($annualTypes as $type) {
                AnnualIncentiveAssignment::query()->updateOrCreate(
                    [
                        'academic_year_id' => $validated['academicYearId'],
                        'employee_id' => $validated['employeeId'],
                        'branch_incentive_id' => $type->id,
                    ],
                    [
                        'branch_id' => $branchId,
                        'amount' => $validated['annualIncentiveAmounts'][$type->id] ?? 0,
                    ]
                );
            }
        });

        $this->loadAnnualIncentiveAmounts();
        session()->flash('success', 'Nilai insentif tahunan pegawai berhasil disimpan.');
    }

    private function effectiveBranchId(): ?string
    {
        $user = auth()->user();

        return in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? ($this->branchId ?: null) : ($user->branch_id ?: null);
    }

    private function authorizeSettingsManagement(): void
    {
        abort_unless(auth()->user()->role === 'keuangan_pusat', 403);
    }

    private function authorizeIncentiveDefinitionManagement(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
    }

    private function authorizePageAccess(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat', 'keuangan'], true), 403);
    }

    private function loadBranchSettings(): void
    {
        $branch = $this->effectiveBranchId() ? Branch::query()->find($this->effectiveBranchId()) : null;
        $this->monthlyTransportAllowance = (string) ($branch?->monthly_transport_allowance ?? '0');
        $this->substitutionAllowancePerPeriod = (string) ($branch?->substitution_allowance_per_period ?? '0');
    }

    private function loadTeacherHourlyRate(): void
    {
        $rate = $this->effectiveBranchId() && $this->academicYearId
            ? TeacherHourlyRate::query()
                ->where('branch_id', $this->effectiveBranchId())
                ->where('academic_year_id', $this->academicYearId)
                ->value('amount')
            : null;
        $this->teacherHourlyRate = (string) ($rate ?? '0');
    }

    private function loadIncentiveDefinitions(): void
    {
        $branchId = $this->effectiveBranchId();
        $this->incentiveDefinitions = $branchId
            ? BranchIncentive::query()
                ->where('branch_id', $branchId)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (BranchIncentive $incentive): array => [
                    $incentive->id => [
                        'name' => $incentive->name,
                        'calculation_type' => $incentive->calculation_type,
                        'default_amount' => (string) $incentive->default_amount,
                    ],
                ])->all()
            : [];
    }

    private function loadAnnualIncentiveAmounts(): void
    {
        $this->annualIncentiveAmounts = [];
        $branchId = $this->effectiveBranchId();
        if (! $branchId || ! $this->employeeId || ! $this->academicYearId) {
            return;
        }

        $this->annualIncentiveAmounts = AnnualIncentiveAssignment::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $this->employeeId)
            ->where('academic_year_id', $this->academicYearId)
            ->whereHas('incentive', fn ($query) => $query
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->where('calculation_type', 'ANNUAL'))
            ->pluck('amount', 'branch_incentive_id')
            ->map(fn ($amount): string => (string) $amount)
            ->all();

        foreach (BranchIncentive::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('calculation_type', 'ANNUAL')
            ->pluck('id') as $id) {
            $this->annualIncentiveAmounts[$id] ??= '0';
        }
    }

    public function render()
    {
        $this->authorizePageAccess();
        $user = auth()->user();
        $branchId = $this->effectiveBranchId();
        $branches = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true) ? Branch::query()->orderBy('name')->get() : collect();
        $employees = $branchId
            ? Employee::query()->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'nip', 'type'])
            : collect();
        $incentives = $branchId
            ? BranchIncentive::query()->where('branch_id', $branchId)->orderBy('name')->get()
            : collect();
        $annualIncentives = $incentives->where('is_active', true)->where('calculation_type', 'ANNUAL');
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get();

        return view('livewire.incentive-settings', compact('branches', 'employees', 'incentives', 'annualIncentives', 'academicYears'))
            ->layout('layouts.app', ['title' => 'Pengaturan Insentif']);
    }
}

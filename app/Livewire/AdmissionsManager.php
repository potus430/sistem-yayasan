<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\ClassCapacity;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class AdmissionsManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 25;

    public bool $showForm = false;

    public string $editingId = '';

    public string $formAcademicYearId = '';

    public string $formType = 'ADMISSION';

    public string $formName = '';

    public string $formNisn = '';

    public string $formGender = '';

    public string $formBirthPlace = '';

    public string $formBirthDate = '';

    public string $formGuardianName = '';

    public string $formGuardianPhone = '';

    public string $formAddress = '';

    public string $formPreviousSchool = '';

    public string $formAppliedClass = '';

    public string $formNotes = '';

    public bool $showCapacityForm = false;

    public string $capacityClassName = '';

    public string $capacityValue = '';

    /** @var array<string, string> */
    public array $capacityInputs = [];

    public function mount(): void
    {
        $this->authorizeBranchAdmin();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function createApplication(string $type = 'ADMISSION'): void
    {
        $this->authorizeBranchAdmin();
        abort_unless(in_array($type, ['ADMISSION', 'TRANSFER'], true), 422);
        $this->resetForm();
        $this->formAcademicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->formType = $type;
        $this->showForm = true;
    }

    public function editApplication(string $applicationId): void
    {
        $this->authorizeBranchAdmin();
        $application = $this->branchApplications()->findOrFail($applicationId);
        abort_if(in_array($application->status, ['ACCEPTED', 'REJECTED'], true), 403, 'Pendaftaran yang sudah selesai tidak dapat diubah.');

        $this->editingId = $application->id;
        $this->formAcademicYearId = (string) ($application->academic_year_id ?? '');
        $this->formType = $application->type;
        $this->formName = $application->name;
        $this->formNisn = (string) ($application->nisn ?? '');
        $this->formGender = (string) ($application->gender ?? '');
        $this->formBirthPlace = (string) ($application->birth_place ?? '');
        $this->formBirthDate = $application->birth_date?->format('Y-m-d') ?? '';
        $this->formGuardianName = $application->guardian_name;
        $this->formGuardianPhone = $application->guardian_phone;
        $this->formAddress = $application->address;
        $this->formPreviousSchool = (string) ($application->previous_school ?? '');
        $this->formAppliedClass = $application->applied_class;
        $this->formNotes = (string) ($application->notes ?? '');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveApplication(): void
    {
        $this->authorizeBranchAdmin();
        $branchId = (string) auth()->user()->branch_id;
        $application = $this->editingId
            ? $this->branchApplications()->findOrFail($this->editingId)
            : new AdmissionApplication;
        abort_if(in_array($application->status, ['ACCEPTED', 'REJECTED'], true), 403, 'Pendaftaran yang sudah selesai tidak dapat diubah.');
        $type = $application->exists ? $application->type : $this->formType;
        $activeYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        if ($activeYearId === '') {
            $this->addError('formAcademicYearId', 'Tahun ajaran aktif belum ditetapkan.');

            return;
        }

        $validated = $this->validate([
            'formType' => ['required', Rule::in([$type])],
            'formName' => ['required', 'string', 'max:255'],
            'formNisn' => [
                'nullable', 'string', 'max:30',
                Rule::unique('admission_applications', 'nisn')->where('branch_id', $branchId)->ignore($application->id ?: null),
                Rule::unique('students', 'nisn')->where('branch_id', $branchId),
            ],
            'formGender' => ['nullable', Rule::in(['LAKI_LAKI', 'PEREMPUAN'])],
            'formBirthPlace' => ['nullable', 'string', 'max:100'],
            'formBirthDate' => ['nullable', 'date', 'before_or_equal:today'],
            'formGuardianName' => ['required', 'string', 'max:255'],
            'formGuardianPhone' => ['required', 'string', 'max:30'],
            'formAddress' => ['required', 'string', 'max:2000'],
            'formPreviousSchool' => [$this->formType === 'TRANSFER' ? 'required' : 'nullable', 'string', 'max:255'],
            'formAppliedClass' => ['required', 'string', 'max:50'],
            'formNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->fill([
            'branch_id' => $branchId,
            'academic_year_id' => $activeYearId,
            'type' => $validated['formType'],
            'name' => trim($validated['formName']),
            'nisn' => $validated['formNisn'] ? trim($validated['formNisn']) : null,
            'gender' => $validated['formGender'] ?: null,
            'birth_place' => $validated['formBirthPlace'] ? trim($validated['formBirthPlace']) : null,
            'birth_date' => $validated['formBirthDate'] ?: null,
            'guardian_name' => trim($validated['formGuardianName']),
            'guardian_phone' => trim($validated['formGuardianPhone']),
            'address' => trim($validated['formAddress']),
            'previous_school' => $validated['formPreviousSchool'] ? trim($validated['formPreviousSchool']) : null,
            'applied_class' => trim($validated['formAppliedClass']),
            'notes' => $validated['formNotes'] ? trim($validated['formNotes']) : null,
        ]);

        if (! $application->exists) {
            $application->registration_number = ($application->type === 'TRANSFER' ? 'PIND-' : 'PPDB-').now()->format('y').'-'.strtoupper(Str::random(8));
            $application->status = 'REGISTERED';
        }
        $application->save();

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', 'Pendaftaran berhasil disimpan.');
    }

    public function advanceApplication(string $applicationId, string $nextStatus): void
    {
        $this->authorizeBranchAdmin();
        $this->resetValidation();
        abort_unless(in_array($nextStatus, ['PASSED', 'ACCEPTED', 'REJECTED'], true), 422);
        $applicationType = $this->branchApplications()->whereKey($applicationId)->value('type');

        DB::transaction(function () use ($applicationId, $nextStatus): void {
            $application = $this->branchApplications()->lockForUpdate()->findOrFail($applicationId);
            $allowed = match ($application->status) {
                'REGISTERED' => $application->type === 'TRANSFER' ? ['ACCEPTED', 'REJECTED'] : ['PASSED', 'REJECTED'],
                'PASSED' => ['ACCEPTED', 'REJECTED'],
                default => [],
            };
            abort_unless(in_array($nextStatus, $allowed, true), 422, 'Status pendaftaran tidak dapat diproses dari tahap ini.');

            if ($nextStatus === 'ACCEPTED') {
                if (! $application->academic_year_id
                    || ! AcademicYear::query()->whereKey($application->academic_year_id)->where('is_active', true)->exists()) {
                    $this->addError('application-'.$application->id, 'Tahun ajaran aktif pendaftaran sudah berubah. Perbarui data pendaftaran sebelum menerima siswa.');

                    return;
                }

                if (! $application->nisn) {
                    $this->addError('application-'.$application->id, 'Isi NISN calon sebelum menerima pendaftaran.');

                    return;
                }

                if (Student::query()
                    ->where('branch_id', $application->branch_id)
                    ->where('nisn', $application->nisn)
                    ->exists()) {
                    $this->addError('application-'.$application->id, 'NISN ini sudah terdaftar sebagai siswa di cabang.');

                    return;
                }

                $capacity = ClassCapacity::query()
                    ->where('branch_id', $application->branch_id)
                    ->where('academic_year_id', $application->academic_year_id)
                    ->where('class_name', $application->applied_class)
                    ->lockForUpdate()
                    ->first();
                if (! $capacity) {
                    $this->addError('application-'.$application->id, 'Kapasitas kelas tujuan belum diatur untuk tahun ajaran aktif.');

                    return;
                }

                $enrolled = Student::query()
                    ->where('branch_id', $application->branch_id)
                    ->where('current_class', $application->applied_class)
                    ->count();
                if ($enrolled >= $capacity->capacity) {
                    $this->addError('application-'.$application->id, 'Kelas tujuan sudah mencapai kapasitas maksimum. Pilih kelas lain atau perbarui kapasitas.');

                    return;
                }

                SchoolClass::query()->firstOrCreate([
                    'branch_id' => $application->branch_id,
                    'name' => $application->applied_class,
                ]);
                $student = Student::query()->create([
                    'branch_id' => $application->branch_id,
                    'nisn' => $application->nisn,
                    'name' => $application->name,
                    'current_class' => $application->applied_class,
                    'admission_academic_year_id' => $application->academic_year_id,
                    'enrollment_type' => $application->type === 'TRANSFER' ? 'TRANSFER' : 'NEW',
                    'gender' => $application->gender,
                    'birth_place' => $application->birth_place,
                    'birth_date' => $application->birth_date,
                    'guardian_name' => $application->guardian_name,
                    'guardian_phone' => $application->guardian_phone,
                    'address' => $application->address,
                    'previous_school' => $application->previous_school,
                ]);
                $application->student_id = $student->id;
            }

            $application->status = $nextStatus;
            $application->save();
        });

        if (! $this->getErrorBag()->isNotEmpty()) {
            session()->flash('success', match ($nextStatus) {
                'PASSED' => 'Calon siswa dinyatakan lolos seleksi.',
                'ACCEPTED' => $applicationType === 'TRANSFER'
                    ? 'Siswa pindahan diterima. Data siswa dan kelas berhasil dibuat untuk sinkronisasi.'
                    : 'Siswa diterima. Data siswa dan kelas berhasil dibuat untuk sinkronisasi.',
                'REJECTED' => 'Pendaftaran ditandai tidak lolos.',
            });
        }
    }

    public function openCapacityForm(): void
    {
        $this->authorizeBranchAdmin();
        $this->capacityInputs = [];
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        if ($activeYearId) {
            $classes = SchoolClass::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->get(['id', 'name']);
            $capacities = ClassCapacity::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('academic_year_id', $activeYearId)
                ->pluck('capacity', 'class_name');
            foreach ($classes as $class) {
                $this->capacityInputs[$class->id] = (string) ($capacities[$class->name] ?? '');
            }
        }
        $this->showCapacityForm = true;
    }

    public function saveClassCapacity(string $schoolClassId): void
    {
        $this->authorizeBranchAdmin();
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        abort_unless($activeYearId, 422, 'Tahun ajaran aktif belum ditetapkan.');
        $schoolClass = SchoolClass::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->findOrFail($schoolClassId);
        $capacityInput = "capacityInputs.{$schoolClassId}";
        $validated = $this->validate([
            $capacityInput => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $requestedCapacity = (int) $validated['capacityInputs'][$schoolClassId];

        $updated = DB::transaction(function () use ($activeYearId, $schoolClass, $schoolClassId, $requestedCapacity): bool {
            $capacity = ClassCapacity::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('academic_year_id', $activeYearId)
                ->where('class_name', $schoolClass->name)
                ->lockForUpdate()
                ->first();
            $enrolled = Student::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('current_class', $schoolClass->name)
                ->count();
            if ($requestedCapacity < $enrolled) {
                $this->addError("capacityInputs.{$schoolClassId}", "Kapasitas tidak boleh lebih kecil dari {$enrolled} siswa yang sudah ada.");

                return false;
            }

            if ($capacity) {
                $capacity->update(['capacity' => $requestedCapacity]);
            } else {
                ClassCapacity::query()->create([
                    'branch_id' => auth()->user()->branch_id,
                    'academic_year_id' => $activeYearId,
                    'class_name' => $schoolClass->name,
                    'capacity' => $requestedCapacity,
                ]);
            }

            return true;
        });
        if (! $updated) {
            return;
        }
        session()->flash('success', "Kapasitas kelas {$schoolClass->name} berhasil diperbarui.");
    }

    public function createClassCapacity(): void
    {
        $this->authorizeBranchAdmin();
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        abort_unless($activeYearId, 422, 'Tahun ajaran aktif belum ditetapkan.');
        $validated = $this->validate([
            'capacityClassName' => [
                'required', 'string', 'max:50',
                Rule::unique('school_classes', 'name')->where('branch_id', auth()->user()->branch_id),
            ],
            'capacityValue' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $className = trim($validated['capacityClassName']);
        $enrolled = Student::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->where('current_class', $className)
            ->count();
        if ((int) $validated['capacityValue'] < $enrolled) {
            $this->addError('capacityValue', "Kapasitas tidak boleh lebih kecil dari {$enrolled} siswa yang sudah ada di kelas tersebut.");

            return;
        }

        DB::transaction(function () use ($activeYearId, $className, $validated): void {
            SchoolClass::query()->create([
                'branch_id' => auth()->user()->branch_id,
                'name' => $className,
            ]);
            ClassCapacity::query()->create([
                'branch_id' => auth()->user()->branch_id,
                'academic_year_id' => $activeYearId,
                'class_name' => $className,
                'capacity' => $validated['capacityValue'],
            ]);
        });
        $this->reset(['capacityClassName', 'capacityValue']);
        $this->resetValidation();
        session()->flash('success', "Kelas {$className} dan kapasitasnya berhasil ditambahkan.");
    }

    private function authorizeBranchAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin_cabang' && auth()->user()?->branch_id, 403);
    }

    private function branchApplications(): Builder
    {
        return AdmissionApplication::query()->where('branch_id', auth()->user()->branch_id);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'formAcademicYearId',
            'formType',
            'formName',
            'formNisn',
            'formGender',
            'formBirthPlace',
            'formBirthDate',
            'formGuardianName',
            'formGuardianPhone',
            'formAddress',
            'formPreviousSchool',
            'formAppliedClass',
            'formNotes',
        ]);
        $this->resetValidation();
    }

    public function render()
    {
        $this->authorizeBranchAdmin();
        $query = $this->branchApplications()
            ->with(['academicYear:id,name', 'student:id'])
            ->when($this->statusFilter !== '', fn (Builder $builder) => $builder->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function (Builder $builder): void {
                $term = '%'.trim($this->search).'%';
                $builder->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', $term)
                    ->orWhere('nisn', 'like', $term)
                    ->orWhere('registration_number', 'like', $term)
                    ->orWhere('guardian_name', 'like', $term)
                    ->orWhere('guardian_phone', 'like', $term));
            });
        $counts = $this->branchApplications()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $applications = $query->latest()->paginate($this->perPage);
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_active']);
        $classes = SchoolClass::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('name')
            ->pluck('name');
        $schoolClasses = SchoolClass::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $classCapacities = $activeYear
            ? ClassCapacity::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('academic_year_id', $activeYear->id)
                ->get()
                ->keyBy('class_name')
            : collect();
        $classAvailability = $classes->mapWithKeys(function (string $className) use ($classCapacities): array {
            $capacity = $classCapacities->get($className);
            $enrolled = Student::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('current_class', $className)
                ->count();

            return [$className => [
                'capacity' => $capacity?->capacity,
                'enrolled' => $enrolled,
                'available' => $capacity ? max(0, $capacity->capacity - $enrolled) : null,
            ]];
        });

        return view('livewire.admissions-manager', compact('academicYears', 'applications', 'classes', 'schoolClasses', 'counts', 'activeYear', 'classAvailability'))
            ->layout('layouts.app', ['title' => 'Pendaftaran siswa']);
    }
}

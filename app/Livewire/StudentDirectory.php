<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class StudentDirectory extends Component
{
    use WithPagination;

    public string $branchId = '';

    public string $search = '';

    public string $className = '';

    public int $perPage = 25;

    public bool $showForm = false;

    public string $editingId = '';

    public string $formBranchId = '';

    public string $formNisn = '';

    public string $formName = '';

    public string $formCurrentClass = '';

    public function mount(): void
    {
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = (string) auth()->user()->branch_id;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['branchId', 'search', 'className'], true)) {
            $this->resetPage();
        }

        if ($property === 'formBranchId') {
            $this->formCurrentClass = '';
        }
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function createStudent(): void
    {
        $this->authorizeManagement();
        $this->resetForm();
        $user = auth()->user();
        $this->formBranchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $this->showForm = true;
    }

    public function editStudent(string $studentId): void
    {
        $this->authorizeManagement();
        $student = $this->scopedStudents()->findOrFail($studentId);
        $this->editingId = $student->id;
        $this->formBranchId = $student->branch_id;
        $this->formNisn = $student->nisn;
        $this->formName = $student->name;
        $this->formCurrentClass = $student->current_class;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveStudent(): void
    {
        $this->authorizeManagement();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->formBranchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum menyimpan siswa.');

        $validated = $this->validate([
            'formBranchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'formNisn' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nisn')->where('branch_id', $branchId)->ignore($this->editingId ?: null),
            ],
            'formName' => ['required', 'string', 'max:255'],
            'formCurrentClass' => [
                'required', 'string', 'max:50',
                Rule::exists('school_classes', 'name')->where('branch_id', $branchId),
            ],
        ]);

        $student = $this->editingId ? $this->scopedStudents()->findOrFail($this->editingId) : new Student;
        if ($student->exists && $student->branch_id !== $branchId
            && DB::table('spp_billings')->where('student_id', $student->id)->exists()) {
            $this->addError('formBranchId', 'Cabang siswa tidak dapat dipindahkan karena sudah memiliki riwayat tagihan SPP.');

            return;
        }

        $student->fill([
            'branch_id' => $branchId,
            'nisn' => trim($validated['formNisn']),
            'name' => trim($validated['formName']),
            'current_class' => $validated['formCurrentClass'],
        ])->save();

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', 'Data siswa berhasil disimpan.');
    }

    public function deleteStudent(string $studentId): void
    {
        $this->authorizeManagement();
        $student = $this->scopedStudents()->findOrFail($studentId);

        if (DB::table('spp_billings')->where('student_id', $student->id)->exists()) {
            session()->flash('error', 'Siswa tidak dapat dihapus karena memiliki riwayat tagihan SPP.');

            return;
        }

        $student->delete();
        session()->flash('success', 'Data siswa berhasil dihapus.');
    }

    private function scopedStudents(): Builder
    {
        $user = auth()->user();

        return Student::query()->when(
            $user->role !== 'admin_pusat',
            fn (Builder $query) => $query->where('branch_id', $user->branch_id)
        );
    }

    private function authorizeManagement(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true), 403);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'formBranchId', 'formNisn', 'formName', 'formCurrentClass']);
        $this->resetValidation();
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? ($this->branchId ?: null) : $user->branch_id;
        $query = Student::query()
            ->with('branch:id,name,level')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($this->className !== '', fn (Builder $query) => $query->where('current_class', $this->className))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', $term)
                    ->orWhere('nisn', 'like', $term));
            });

        $totalStudents = (clone $query)->count();
        $classes = $branchId
            ? SchoolClass::query()->where('branch_id', $branchId)->orderBy('name')->pluck('name')
            : Student::query()->select('current_class')->distinct()->orderBy('current_class')->pluck('current_class');
        $formClasses = $this->formBranchId
            ? SchoolClass::query()->where('branch_id', $this->formBranchId)->orderBy('name')->pluck('name')
            : collect();
        $students = $query->orderBy('branch_id')->orderBy('current_class')->orderBy('name')->paginate($this->perPage);
        $branches = $user->role === 'admin_pusat' ? Branch::query()->orderBy('name')->get(['id', 'name', 'level']) : collect();

        return view('livewire.student-directory', compact('branches', 'classes', 'formClasses', 'students', 'totalStudents'))
            ->layout('layouts.app', ['title' => 'Data siswa']);
    }
}

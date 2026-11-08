<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class StudentDirectory extends Component
{
    use HasDateRangeFilter, WithPagination;

    public string $branchId = '';

    public string $search = '';

    public string $className = '';

    public int $perPage = 25;

    public bool $showForm = false;

    public ?string $selectedStudentId = null;

    public string $editingId = '';

    public string $formBranchId = '';

    public string $formNisn = '';

    public string $formName = '';

    public string $formCurrentClass = '';

    public function mount(): void
    {
        $this->authorizeViewing();
        if (auth()->user()->role === 'admin_cabang') {
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

    public function showDetails(string $studentId): void
    {
        $this->authorizeViewing();
        $this->scopedStudents()->findOrFail($studentId);
        $this->selectedStudentId = $studentId;
    }

    public function closeDetails(): void
    {
        $this->selectedStudentId = null;
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

        StudentDocument::query()->where('student_id', $student->id)->each(function (StudentDocument $document): void {
            $disk = Storage::disk($document->disk);
            if ($disk->exists($document->path) && ! $disk->delete($document->path)) {
                throw new RuntimeException('Dokumen siswa gagal dihapus dari penyimpanan privat.');
            }
            $document->delete();
        });
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
        abort_unless(auth()->user()->role === 'admin_cabang', 403);
    }

    private function authorizeViewing(): void
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
        $this->authorizeViewing();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? ($this->branchId ?: null) : $user->branch_id;
        $query = Student::query()
            ->with('branch:id,name,level')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query, 'created_at'))
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
        $studentIds = $students->getCollection()->pluck('id');
        $photos = StudentDocument::query()
            ->whereIn('student_id', $studentIds)
            ->where('type', 'PAS_PHOTO')
            ->latest('created_at')
            ->get()
            ->filter(fn (StudentDocument $document): bool => in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                && Storage::disk($document->disk)->exists($document->path))
            ->unique('student_id')
            ->keyBy('student_id');
        $branches = $user->role === 'admin_pusat'
            ? Branch::query()->whereIn('level', ['TK', 'SD', 'SMP', 'SMA'])->orderBy('name')->get(['id', 'name', 'level'])
            : collect();
        $selectedStudent = $this->selectedStudentId
            ? $this->scopedStudents()->with(['branch', 'admissionAcademicYear', 'documents'])->findOrFail($this->selectedStudentId)
            : null;

        return view('livewire.student-directory', compact('branches', 'classes', 'formClasses', 'photos', 'selectedStudent', 'students', 'totalStudents'))
            ->layout('layouts.app', ['title' => 'Data siswa']);
    }
}

<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\SchoolRoom;
use App\Models\SchoolSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SchoolStructureManager extends Component
{
    public string $branchId = '';

    public string $kind = 'class';

    public string $editingId = '';

    public string $name = '';

    public bool $showForm = false;

    public function mount(): void
    {
        $this->authorizeManagement();
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = (string) auth()->user()->branch_id;
        }
    }

    public function updatedBranchId(): void
    {
        $this->reset(['editingId', 'name', 'showForm']);
        $this->kind = 'class';
        $this->resetValidation();
    }

    public function create(string $kind): void
    {
        $this->authorizeManagement();
        $this->validateKind($kind);
        $this->reset(['editingId', 'name']);
        $this->kind = $kind;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(string $kind, string $id): void
    {
        $this->authorizeManagement();
        $this->validateKind($kind);
        $record = $this->queryFor($kind)->findOrFail($id);
        $this->kind = $kind;
        $this->editingId = $record->id;
        $this->name = $record->name;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $this->validateKind($this->kind);
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum mengelola master sekolah.');

        $table = match ($this->kind) {
            'class' => 'school_classes',
            'room' => 'school_rooms',
            'subject' => 'school_subjects',
        };
        $nameMaxLength = $this->kind === 'subject' ? 100 : 50;
        $validated = $this->validate([
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'name' => [
                'required', 'string', "max:{$nameMaxLength}",
                Rule::unique($table, 'name')->where('branch_id', $branchId)->ignore($this->editingId ?: null),
            ],
        ]);

        $record = $this->editingId ? $this->queryFor($this->kind)->findOrFail($this->editingId) : $this->modelFor($this->kind);
        if ($record->exists && $record->branch_id !== $branchId) {
            $this->addError('branchId', 'Data kelas atau ruangan tidak dapat dipindahkan antar cabang.');

            return;
        }

        DB::transaction(function () use ($branchId, $validated): void {
            $record = $this->editingId ? $this->queryFor($this->kind)->findOrFail($this->editingId) : $this->modelFor($this->kind);
            $oldName = $record->name;
            $newName = trim($validated['name']);
            $record->fill(['branch_id' => $branchId, 'name' => $newName])->save();

            if ($this->editingId && $oldName !== $newName) {
                if ($this->kind === 'class') {
                    DB::table('students')->where('branch_id', $branchId)->where('current_class', $oldName)->update([
                        'current_class' => $newName,
                        'updated_at' => now(),
                    ]);
                    DB::table('roster_slots')->where('branch_id', $branchId)->where('class_name', $oldName)->update([
                        'class_name' => $newName,
                        'updated_at' => now(),
                    ]);
                } elseif ($this->kind === 'room') {
                    DB::table('roster_slots')->where('branch_id', $branchId)->where('room', $oldName)->update([
                        'room' => $newName,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('roster_slots')->where('branch_id', $branchId)->where('subject', $oldName)->update([
                        'subject' => $newName,
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->showForm = false;
        $this->reset(['editingId', 'name']);
        session()->flash('success', match ($this->kind) {
            'class' => 'Data kelas berhasil disimpan.',
            'room' => 'Data ruangan berhasil disimpan.',
            'subject' => 'Mata pelajaran berhasil disimpan.',
        });
    }

    public function delete(string $kind, string $id): void
    {
        $this->authorizeManagement();
        $this->validateKind($kind);
        $record = $this->queryFor($kind)->findOrFail($id);
        $isInUse = match ($kind) {
            'class' => DB::table('students')->where('branch_id', $record->branch_id)->where('current_class', $record->name)->exists()
                || DB::table('roster_slots')->where('branch_id', $record->branch_id)->where('class_name', $record->name)->exists(),
            'room' => DB::table('roster_slots')->where('branch_id', $record->branch_id)->where('room', $record->name)->exists(),
            'subject' => DB::table('roster_slots')->where('branch_id', $record->branch_id)->where('subject', $record->name)->exists(),
        };

        if ($isInUse) {
            session()->flash('error', match ($kind) {
                'class' => 'Kelas tidak dapat dihapus karena masih digunakan siswa atau roster.',
                'room' => 'Ruangan tidak dapat dihapus karena masih digunakan pada roster.',
                'subject' => 'Mata pelajaran tidak dapat dihapus karena masih digunakan pada roster.',
            });

            return;
        }

        $record->delete();
        session()->flash('success', match ($kind) {
            'class' => 'Data kelas berhasil dihapus.',
            'room' => 'Data ruangan berhasil dihapus.',
            'subject' => 'Mata pelajaran berhasil dihapus.',
        });
    }

    private function queryFor(string $kind): Builder
    {
        $user = auth()->user();
        $model = $this->modelFor($kind);

        return $model->newQuery()->when(
            $user->role !== 'admin_pusat' || $this->branchId !== '',
            fn (Builder $query) => $query->where('branch_id', $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id)
        );
    }

    private function modelFor(string $kind): SchoolClass|SchoolRoom
    {
        return match ($kind) {
            'class' => new SchoolClass,
            'room' => new SchoolRoom,
            'subject' => new SchoolSubject,
        };
    }

    private function validateKind(string $kind): void
    {
        abort_unless(in_array($kind, ['class', 'room', 'subject'], true), 422);
    }

    private function authorizeManagement(): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true), 403);
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $branches = $user->role === 'admin_pusat' ? Branch::query()->orderBy('name')->get(['id', 'name', 'level']) : collect();
        $classes = $branchId ? SchoolClass::query()->where('branch_id', $branchId)->orderBy('name')->get() : collect();
        $rooms = $branchId ? SchoolRoom::query()->where('branch_id', $branchId)->orderBy('name')->get() : collect();
        $subjects = $branchId ? SchoolSubject::query()->where('branch_id', $branchId)->orderBy('name')->get() : collect();

        return view('livewire.school-structure-manager', compact('branches', 'classes', 'rooms', 'subjects'))
            ->layout('layouts.app', ['title' => 'Data kelas dan ruangan']);
    }
}

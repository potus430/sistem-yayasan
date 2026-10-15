<?php

namespace App\Livewire;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BranchDirectory extends Component
{
    public bool $showForm = false;

    public string $editingId = '';

    public string $name = '';

    public string $level = '';

    public string $address = '';

    public string $leaderName = '';

    public string $leaderTitle = '';

    public string $phone = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
    }

    public function create(): void
    {
        $this->authorizeManagement();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $branchId): void
    {
        $this->authorizeManagement();
        $branch = Branch::query()->findOrFail($branchId);
        $this->editingId = $branch->id;
        $this->name = $branch->name;
        $this->level = $branch->level;
        $this->address = (string) $branch->address;
        $this->leaderName = (string) $branch->leader_name;
        $this->leaderTitle = (string) $branch->leader_title;
        $this->phone = (string) $branch->phone;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($this->editingId ?: null)],
            'level' => ['required', Rule::in(['TK', 'SD', 'SMP', 'SMA'])],
            'address' => ['nullable', 'string', 'max:2000'],
            'leaderName' => ['nullable', 'string', 'max:255'],
            'leaderTitle' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $branch = $this->editingId ? Branch::query()->findOrFail($this->editingId) : new Branch;
        $branch->fill([
            'name' => trim($validated['name']),
            'level' => $validated['level'],
            'address' => $validated['address'] ? trim($validated['address']) : null,
            'leader_name' => $validated['leaderName'] ? trim($validated['leaderName']) : null,
            'leader_title' => $validated['leaderTitle'] ? trim($validated['leaderTitle']) : null,
            'phone' => $validated['phone'] ? trim($validated['phone']) : null,
        ])->save();

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', 'Profil cabang berhasil disimpan.');
    }

    public function delete(string $branchId): void
    {
        $this->authorizeManagement();
        $branch = Branch::query()->findOrFail($branchId);
        $hasData = DB::table('employees')->where('branch_id', $branch->id)->exists()
            || DB::table('students')->where('branch_id', $branch->id)->exists()
            || DB::table('users')->where('branch_id', $branch->id)->exists();

        if ($hasData) {
            session()->flash('error', 'Cabang tidak dapat dihapus karena masih memiliki pegawai, siswa, atau akun pengguna.');

            return;
        }

        $branch->delete();
        session()->flash('success', 'Cabang berhasil dihapus.');
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'level', 'address', 'leaderName', 'leaderTitle', 'phone']);
        $this->resetValidation();
    }

    public function render()
    {
        $branches = Branch::query()
            ->withCount(['employees', 'students'])
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.branch-directory', compact('branches'))
            ->layout('layouts.app', ['title' => 'Data cabang']);
    }
}

<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UserDirectory extends Component
{
    use HasDateRangeFilter, WithPagination;

    public bool $showForm = false;

    public string $editingId = '';

    public string $name = '';

    public string $email = '';

    public string $role = 'admin_cabang';

    public string $branchId = '';

    public string $password = '';

    public bool $isActive = true;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManagement();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $userId): void
    {
        $this->authorizeManagement();
        $user = User::query()->findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->branchId = (string) $user->branch_id;
        $this->isActive = $user->is_active;
        $this->password = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $user = $this->editingId ? User::query()->findOrFail($this->editingId) : new User;
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId ?: null)],
            'role' => ['required', Rule::in(['admin_pusat', 'keuangan_pusat', 'admin_cabang', 'keuangan', 'guru', 'staff', 'staff_pusat'])],
            'branchId' => [
                Rule::requiredIf(! in_array($this->role, ['admin_pusat', 'keuangan_pusat', 'staff_pusat'], true)),
                'nullable',
                'uuid',
                Rule::exists('branches', 'id')->whereIn('level', ['TK', 'SD', 'SMP', 'SMA']),
            ],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:12', 'max:255'],
            'isActive' => ['boolean'],
        ]);

        if ($user->exists && $user->role === 'admin_pusat' && $validated['role'] !== 'admin_pusat'
            && User::query()->where('role', 'admin_pusat')->where('is_active', true)->count() <= 1) {
            $this->addError('role', 'Setidaknya satu admin pusat aktif harus dipertahankan.');

            return;
        }
        if ($user->exists && $user->role === 'admin_pusat' && $user->is_active && ! $validated['isActive']
            && User::query()->where('role', 'admin_pusat')->where('is_active', true)->count() <= 1) {
            $this->addError('isActive', 'Setidaknya satu admin pusat aktif harus dipertahankan.');

            return;
        }

        $attributes = [
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'role' => $validated['role'],
            'branch_id' => in_array($validated['role'], ['admin_pusat', 'keuangan_pusat', 'staff_pusat'], true) ? null : $validated['branchId'],
            'is_active' => $validated['isActive'],
        ];
        if ($validated['password'] ?? null) {
            $attributes['password'] = $validated['password'];
        }
        $user->fill($attributes)->save();

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', 'Akun pengguna berhasil disimpan.');
    }

    public function toggleActive(string $userId): void
    {
        $this->authorizeManagement();
        $user = User::query()->findOrFail($userId);
        abort_if($user->is(auth()->user()), 422, 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.');

        $blocked = false;
        DB::transaction(function () use ($user, &$blocked): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($lockedUser->is_active && $lockedUser->role === 'admin_pusat'
                && User::query()->where('role', 'admin_pusat')->where('is_active', true)->count() <= 1) {
                $blocked = true;

                return;
            }

            $lockedUser->update(['is_active' => ! $lockedUser->is_active]);
        });

        if ($blocked) {
            $this->addError('isActive', 'Setidaknya satu admin pusat aktif harus dipertahankan.');

            return;
        }

        session()->flash('success', 'Status akun berhasil diperbarui.');
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'branchId', 'password']);
        $this->role = 'admin_cabang';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        $term = '%'.trim($this->search).'%';
        $users = User::query()
            ->with('branch:id,name')
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $searchQuery) => $searchQuery
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query, 'created_at'))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(25);
        $branches = Branch::query()->whereIn('level', ['TK', 'SD', 'SMP', 'SMA'])->orderBy('name')->get(['id', 'name']);

        return view('livewire.user-directory', compact('users', 'branches'))
            ->layout('layouts.app', ['title' => 'Manajemen akun']);
    }
}

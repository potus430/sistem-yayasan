<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-900 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-200">Keamanan dan akses sistem</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Akun & akses</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Buat akun, tentukan peran dan cabang, lalu nonaktifkan akses yang tidak lagi diperlukan.</p></div><button type="button" wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-bold text-indigo-900 shadow-lg hover:bg-indigo-50"><span class="text-lg leading-none">+</span>Buat akun</button></div>
    </div>
    <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold text-slate-900">Daftar pengguna</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($users->total()) }} akun terdaftar</p></div><input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau email..." class="w-full rounded-xl border-slate-200 sm:max-w-xs"></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3.5">Pengguna</th><th class="px-5 py-3.5">Peran</th><th class="px-5 py-3.5">Cabang</th><th class="px-5 py-3.5">Status</th><th class="px-5 py-3.5 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-indigo-50/30">
                        <td class="whitespace-nowrap px-5 py-4"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 font-bold text-indigo-700">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><p class="font-semibold text-slate-900">{{ $user->name }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></div></div></td>
                        <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">{{ ['admin_pusat' => 'Admin pusat', 'admin_cabang' => 'Admin cabang', 'keuangan' => 'Keuangan', 'guru' => 'Guru', 'staff' => 'Staff'][$user->role] ?? $user->role }}</span></td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $user->branch?->name ?? 'Semua cabang' }}</td>
                        <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="whitespace-nowrap px-5 py-4 text-right"><button type="button" wire:click="edit('{{ $user->id }}')" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</button>@if(! $user->is(auth()->user()))<button type="button" wire:click="toggleActive('{{ $user->id }}')" wire:confirm="{{ $user->is_active ? 'Nonaktifkan akses akun ini?' : 'Aktifkan kembali akun ini?' }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold {{ $user->is_active ? 'text-rose-700 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50' }}">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">Akun tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($users->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>@endif
    </article>

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
            <form wire:submit="save" class="my-auto w-full max-w-2xl space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Pengaturan akses</p><h2 id="user-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Edit akun' : 'Buat akun pengguna' }}</h2></div><button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100">✕</button></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">Nama lengkap<input wire:model="name" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200">@error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700">Email login<input wire:model="email" type="email" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200">@error('email')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700">Peran
                        <select wire:model.live="role" required class="mt-1.5 w-full rounded-xl border-slate-200"><option value="admin_pusat">Admin pusat</option><option value="admin_cabang">Admin cabang</option><option value="keuangan">Keuangan</option><option value="guru">Guru</option><option value="staff">Staff</option></select>@error('role')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    @if($role !== 'admin_pusat')
                        <label class="text-sm font-semibold text-slate-700">Cabang
                            <select wire:model="branchId" required class="mt-1.5 w-full rounded-xl border-slate-200"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>@error('branchId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Kata sandi {{ $editingId ? '(kosongkan jika tidak diubah)' : '' }}
                        <input wire:model="password" type="password" autocomplete="new-password" {{ $editingId ? '' : 'required' }} minlength="12" maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Minimal 12 karakter">
                        @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 text-sm font-semibold text-slate-700 sm:col-span-2"><input wire:model="isActive" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">Akun aktif dan dapat masuk ke sistem</label>
                    @error('isActive')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Batal</button><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan akun</button></div>
            </form>
        </div>
    @endif
</section>

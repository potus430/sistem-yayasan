<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif

    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-200">Organisasi yayasan</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Data cabang</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-violet-100">Kelola profil, alamat, pimpinan, dan kontak setiap sekolah.</p></div>
            <button type="button" wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-bold text-indigo-900 shadow-lg transition hover:bg-violet-50"><span class="text-lg leading-none">+</span>Tambah cabang</button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($branches as $branch)
            <article wire:key="branch-{{ $branch->id }}" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3 bg-gradient-to-r from-slate-50 to-indigo-50/70 p-5">
                    <div class="flex items-center gap-3"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-sm font-black text-white shadow-md shadow-indigo-200">{{ $branch->level }}</span><div><h2 class="font-bold text-slate-900">{{ $branch->name }}</h2><p class="mt-0.5 text-xs text-slate-500">Cabang yayasan</p></div></div>
                    <button type="button" wire:click="edit('{{ $branch->id }}')" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit profil</button>
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex gap-3"><span class="mt-0.5 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg></span><p class="text-sm leading-5 text-slate-600">{{ $branch->address ?: 'Alamat belum diisi' }}</p></div>
                    <div class="flex gap-3"><span class="mt-0.5 text-indigo-400"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span><div><p class="text-sm font-semibold text-slate-800">{{ $branch->leader_name ?: 'Pimpinan belum diisi' }}</p><p class="text-xs text-slate-500">{{ $branch->leader_title ?: 'Pimpinan cabang' }}</p></div></div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                        <span class="text-slate-500">{{ $branch->phone ?: 'Kontak belum diisi' }}</span>
                        <span class="font-medium text-slate-600">{{ number_format($branch->students_count) }} siswa · {{ number_format($branch->employees_count) }} pegawai</span>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center sm:col-span-2 xl:col-span-3"><p class="font-semibold text-slate-800">Belum ada cabang</p><p class="mt-1 text-sm text-slate-500">Tambahkan profil sekolah untuk mulai mengelola data yayasan.</p></div>
        @endforelse
    </div>
    @if($branches->hasPages())<div class="rounded-2xl bg-white p-4 shadow-sm">{{ $branches->links() }}</div>@endif

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="branch-form-title">
            <form wire:submit="save" class="my-auto w-full max-w-2xl space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Profil organisasi</p><h2 id="branch-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Edit profil cabang' : 'Tambah cabang' }}</h2></div><button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100">✕</button></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Nama sekolah/cabang
                        <input wire:model="name" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Contoh: SMA Harapan Bangsa">@error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Jenjang
                        <select wire:model="level" required class="mt-1.5 w-full rounded-xl border-slate-200"><option value="">Pilih jenjang</option><option value="TK">TK</option><option value="SD">SD</option><option value="SMP">SMP</option><option value="SMA">SMA</option></select>@error('level')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Telepon cabang
                        <input wire:model="phone" maxlength="40" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nomor telepon">@error('phone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Alamat lengkap
                        <textarea wire:model="address" rows="2" maxlength="2000" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Jalan, nomor, kelurahan, kota, kode pos"></textarea>@error('address')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Nama pimpinan cabang
                        <input wire:model="leaderName" maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nama kepala sekolah">@error('leaderName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Jabatan pimpinan
                        <input wire:model="leaderTitle" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Contoh: Kepala Sekolah">@error('leaderTitle')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Batal</button><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan profil</button></div>
            </form>
        </div>
    @endif
</section>

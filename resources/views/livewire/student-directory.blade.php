<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-950 via-sky-900 to-indigo-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-100">Direktori peserta didik</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Data siswa</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-sky-100">Temukan siswa berdasarkan nama, NISN, kelas, atau cabang sekolah.</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                <p class="text-xs font-medium text-sky-100">Jumlah sesuai filter</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totalStudents) }} <span class="text-sm font-medium text-sky-100">siswa</span></p>
            </div>
        </div>
    </div>

    <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div><h2 class="text-lg font-bold text-slate-900">Daftar siswa</h2><p class="mt-1 text-sm text-slate-500">Data siswa dikelompokkan menurut cabang dan kelas.</p></div>
                <div class="grid gap-2 sm:grid-cols-2 lg:flex">
                    <a href="{{ route('reports.download', ['report' => 'students', 'branch_id' => $branchId]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Ekspor CSV
                    </a>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-500">Baris
                        <select wire:model.live="perPage" aria-label="Jumlah siswa per halaman" class="rounded-xl border-slate-200 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:ring-sky-200">
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                        </select>
                    </label>
                    <label class="relative block">
                        <span class="sr-only">Cari nama atau NISN</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau NISN..." class="w-full rounded-xl border-slate-200 py-2.5 pl-9 pr-3 text-sm focus:border-sky-400 focus:ring-sky-200 sm:w-60">
                    </label>
                    <select wire:model.live="className" aria-label="Filter kelas" class="rounded-xl border-slate-200 py-2.5 text-sm focus:border-sky-400 focus:ring-sky-200">
                        <option value="">Semua kelas</option>
                        @foreach($classes as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach
                    </select>
                    @if(auth()->user()->role === 'admin_pusat')
                        <select wire:model.live="branchId" aria-label="Filter cabang" class="rounded-xl border-slate-200 py-2.5 text-sm focus:border-sky-400 focus:ring-sky-200">
                            <option value="">Semua cabang</option>
                            @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                        </select>
                    @endif
                    <button type="button" wire:click="createStudent" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Tambah siswa
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50/80">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3.5">Siswa</th><th class="px-5 py-3.5">NISN</th><th class="px-5 py-3.5">Kelas</th><th class="px-5 py-3.5">Cabang sekolah</th><th class="px-5 py-3.5 text-right">Jenjang</th><th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $student)
                        <tr wire:key="student-{{ $student->id }}" class="transition hover:bg-sky-50/40">
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sm font-bold text-sky-700">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                    <div><p class="font-semibold text-slate-900">{{ $student->name }}</p><p class="text-xs text-slate-500">Peserta didik aktif</p></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-600">{{ $student->nisn }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-lg bg-sky-50 px-2.5 py-1.5 text-sm font-semibold text-sky-800">{{ $student->current_class }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-700">{{ $student->branch?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $student->branch?->level ?? '—' }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <button type="button" wire:click="editStudent('{{ $student->id }}')" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50">Edit</button>
                                <button type="button" wire:click="deleteStudent('{{ $student->id }}')" wire:confirm="Hapus data {{ $student->name }}? Siswa dengan riwayat SPP tidak dapat dihapus." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5Z"/><path d="M6 12v5c3.3 2.7 8.7 2.7 12 0v-5"/></svg></div>
                            <p class="mt-3 font-semibold text-slate-800">Data tidak ditemukan</p><p class="mt-1 text-sm text-slate-500">Coba ubah kata pencarian atau filter yang digunakan.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $students->links() }}</div>
        @endif
    </article>

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="student-form-title">
            <form wire:submit="saveStudent" class="my-auto w-full max-w-2xl space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-600">Data peserta didik</p><h2 id="student-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Edit siswa' : 'Tambah siswa' }}</h2></div>
                    <button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @if(auth()->user()->role === 'admin_pusat')
                        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Cabang
                            <select wire:model.live="formBranchId" required class="mt-1.5 w-full rounded-xl border-slate-200">
                                <option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }} · {{ $branch->level }}</option>@endforeach
                            </select>
                            @error('formBranchId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="text-sm font-semibold text-slate-700">Nama lengkap
                        <input wire:model="formName" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nama siswa">
                        @error('formName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">NISN
                        <input wire:model="formNisn" required maxlength="30" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nomor induk siswa nasional">
                        @error('formNisn')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Kelas
                        <select wire:model="formCurrentClass" required class="mt-1.5 w-full rounded-xl border-slate-200">
                            <option value="">Pilih kelas</option>@foreach($formClasses as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach
                        </select>
                        @error('formCurrentClass')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        @if($formBranchId && $formClasses->isEmpty())<span class="mt-1 block text-xs text-amber-700">Belum ada master kelas. Tambahkan kelas melalui menu Kelas & ruangan.</span>@endif
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-60">Simpan data</button>
                </div>
            </form>
        </div>
    @endif
</section>

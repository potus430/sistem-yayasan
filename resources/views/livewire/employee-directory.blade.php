<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-200">Direktori sumber daya manusia</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true) ? 'Pegawai yayasan & sekolah' : 'Guru & pegawai' }}</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-indigo-100">{{ auth()->user()->role === 'admin_pusat' ? 'Kelola profil staff lintas unit dan lihat data guru.' : (auth()->user()->role === 'keuangan_pusat' ? 'Lihat seluruh pegawai dan kelola nominal gaji pokok.' : 'Cari dan tinjau data tenaga pendidik serta staff di sekolah.') }}</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                <p class="text-xs font-medium text-indigo-100">Jumlah sesuai filter</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totalEmployees) }} <span class="text-sm font-medium text-indigo-100">pegawai</span></p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 {{ auth()->user()->role === 'admin_pusat' ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }}">
        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3"><span class="rounded-xl bg-indigo-50 p-2.5 text-indigo-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4.5a4 4 0 0 1 0 7.75M19 14a5 5 0 0 1 2 4v3"/></svg></span><div><p class="text-sm text-slate-500">Guru</p><p class="text-2xl font-bold text-slate-900">{{ number_format($teachersCount) }}</p></div></div>
        </article>
        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3"><span class="rounded-xl bg-sky-50 p-2.5 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/></svg></span><div><p class="text-sm text-slate-500">Staff</p><p class="text-2xl font-bold text-slate-900">{{ number_format($staffCount) }}</p></div></div>
        </article>
        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3"><span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/></svg></span><div><p class="text-sm text-slate-500">Pegawai tetap</p><p class="text-2xl font-bold text-slate-900">{{ number_format($permanentCount) }}</p></div></div>
        </article>
    </div>

    <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div><h2 class="text-lg font-bold text-slate-900">Daftar tenaga kerja</h2><p class="mt-1 text-sm text-slate-500">Informasi pegawai yang terdaftar pada unit kerja.</p></div>
                <div class="grid gap-2 sm:grid-cols-2 lg:flex">
                    <a href="{{ route('reports.download', array_merge(['report' => 'employees', 'branch_id' => $branchId, 'format' => 'pdf'], $this->dateFilterQueryParameters())) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Ekspor PDF</a>
                    <a href="{{ route('reports.download', array_merge(['report' => 'employees', 'branch_id' => $branchId, 'format' => 'xlsx'], $this->dateFilterQueryParameters())) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Ekspor Excel</a>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-500">Baris
                        <select wire:model.live="perPage" aria-label="Jumlah pegawai per halaman" class="rounded-xl border-slate-200 py-2.5 text-sm text-slate-700 focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                        </select>
                    </label>
                    <label class="relative block">
                        <span class="sr-only">Cari nama atau NIP</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau NIP..." class="w-full rounded-xl border-slate-200 py-2.5 pl-9 pr-3 text-sm focus:border-indigo-400 focus:ring-indigo-200 sm:w-56">
                    </label>
                    <select wire:model.live="type" aria-label="Filter jenis pegawai" class="rounded-xl border-slate-200 py-2.5 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                        <option value="">Semua jenis</option><option value="GURU">Guru</option><option value="STAFF">Staff</option>
                    </select>
                    <select wire:model.live="status" aria-label="Filter status pegawai" class="rounded-xl border-slate-200 py-2.5 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                        <option value="">Semua status</option><option value="TETAP">Tetap</option><option value="HONORER">Honorer</option>
                    </select>
                    @if(in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true))
                        <select wire:model.live="branchId" aria-label="Filter cabang" class="rounded-xl border-slate-200 py-2.5 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                            <option value="">Pusat dan semua cabang</option>
                            @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->level === 'YAYASAN' ? 'Yayasan Pusat' : $branch->name }}</option>@endforeach
                        </select>
                    @endif
                    @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true))
                    <button type="button" wire:click="createEmployee" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Tambah pegawai
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-4 pt-4">@include('livewire.partials.date-range-filter')</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50/80">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3.5">Pegawai</th><th class="px-5 py-3.5">NIP</th><th class="px-5 py-3.5">Cabang</th><th class="px-5 py-3.5">Jenis</th><th class="px-5 py-3.5">Status</th><th class="px-5 py-3.5 text-right">Gaji pokok</th><th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        <tr wire:key="employee-{{ $employee->id }}" class="transition hover:bg-indigo-50/40">
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $employee->type === 'GURU' ? 'bg-indigo-100 text-indigo-700' : 'bg-sky-100 text-sky-700' }} text-sm font-bold">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                                    <div><p class="font-semibold text-slate-900">{{ $employee->name }}</p><p class="text-xs text-slate-500">{{ $employee->type === 'GURU' ? 'Tenaga pendidik' : 'Tenaga kependidikan' }} · Terdaftar {{ $employee->created_at?->format('d M Y') ?? '—' }}</p></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-600">{{ $employee->nip }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="font-medium text-slate-700">{{ $employee->branch?->name ?? '—' }}</span><span class="ml-1 text-xs text-slate-400">{{ $employee->branch?->level }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $employee->type === 'GURU' ? 'bg-indigo-50 text-indigo-700' : 'bg-sky-50 text-sky-700' }}">{{ $employee->type === 'GURU' ? 'Guru' : 'Staff' }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $employee->status === 'TETAP' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $employee->status === 'TETAP' ? 'Tetap' : 'Honorer' }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-slate-700">
                                @if(auth()->user()->role === 'keuangan_pusat' && $employee->type === 'STAFF')
                                    <form wire:submit="saveBaseSalary('{{ $employee->id }}')" class="flex items-center justify-end gap-2">
                                        <span>Rp</span><input wire:model="salaryAmounts.{{ $employee->id }}" type="number" min="0" step="0.01" required aria-label="Gaji pokok {{ $employee->name }}" class="w-32 rounded-lg border-slate-200 text-right text-sm">
                                        <button type="submit" class="rounded-lg px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Simpan</button>
                                    </form>
                                    @error("salaryAmounts.{$employee->id}")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                                @elseif($employee->type === 'GURU')
                                    <span class="text-xs font-medium text-indigo-600">Dihitung dari tarif/jam × roster</span>
                                @else
                                    Rp {{ number_format((float) $employee->base_salary, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                @if(auth()->user()->role === 'keuangan_pusat' || (auth()->user()->role === 'admin_pusat' && $employee->type === 'GURU'))
                                    <span class="text-xs text-slate-400">Profil guru dikelola admin cabang</span>
                                @else
                                    <button type="button" wire:click="editEmployee('{{ $employee->id }}')" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</button>
                                    <button type="button" wire:click="deleteEmployee('{{ $employee->id }}')" wire:confirm="Hapus data {{ $employee->name }}? Data yang memiliki riwayat tidak dapat dihapus." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/></svg></div>
                            <p class="mt-3 font-semibold text-slate-800">Data tidak ditemukan</p><p class="mt-1 text-sm text-slate-500">Coba ubah kata pencarian atau filter yang digunakan.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($employees->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $employees->links() }}</div>
        @endif
    </article>

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="employee-form-title">
            <form wire:submit="saveEmployee" class="my-auto w-full max-w-2xl space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Data tenaga kerja</p><h2 id="employee-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Edit pegawai' : 'Tambah pegawai' }}</h2></div>
                    <button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @if(auth()->user()->role === 'admin_pusat')
                        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Cabang
                            <select wire:model="formBranchId" required class="mt-1.5 w-full rounded-xl border-slate-200">
                                <option value="">Pilih unit kerja</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->level === 'YAYASAN' ? 'Yayasan Pusat' : $branch->name }}{{ $branch->level === 'YAYASAN' ? '' : ' · '.$branch->level }}</option>@endforeach
                            </select>
                            @error('formBranchId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="text-sm font-semibold text-slate-700">Nama lengkap
                        <input wire:model="formName" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nama pegawai">
                        @error('formName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">NIP
                        <input wire:model="formNip" required maxlength="50" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Nomor induk pegawai">
                        @error('formNip')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    @if(auth()->user()->role === 'admin_pusat')
                        <input type="hidden" wire:model="formType" value="STAFF">
                        <p class="rounded-xl bg-sky-50 p-3 text-sm text-sky-800">Admin pusat hanya dapat menambah dan mengelola data staff. Data guru dikelola admin cabang.</p>
                    @else
                        <label class="text-sm font-semibold text-slate-700">Jenis
                            <select wire:model="formType" class="mt-1.5 w-full rounded-xl border-slate-200"><option value="GURU">Guru</option><option value="STAFF">Staff</option></select>
                            @error('formType')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="text-sm font-semibold text-slate-700">Status kepegawaian
                        <select wire:model="formStatus" class="mt-1.5 w-full rounded-xl border-slate-200"><option value="TETAP">Tetap</option><option value="HONORER">Honorer</option></select>
                        @error('formStatus')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 sm:col-span-2">Nilai gaji pokok diatur oleh keuangan pusat.</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan data</button>
                </div>
            </form>
        </div>
    @endif
</section>

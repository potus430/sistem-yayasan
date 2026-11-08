<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif

    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-100">Penerimaan peserta didik baru</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Pendaftaran siswa</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-violet-100">Kelola calon siswa dari pendaftaran, hasil seleksi, hingga diterima menjadi siswa dan masuk ke kelas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="openCapacityForm" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/20">Atur kapasitas kelas</button>
                <button type="button" wire:click="createApplication('TRANSFER')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/20">+ Siswa pindahan</button>
                <button type="button" wire:click="createApplication" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-900 shadow-sm hover:bg-violet-50">+ Pendaftaran baru</button>
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-violet-50">
            @if($activeYear)
                Kapasitas berlaku untuk <strong>{{ $activeYear->name }}</strong>. Batas kursi dan siswa aktif per kelas ditampilkan pada daftar; kelas tanpa kapasitas tidak dapat menerima siswa.
            @else
                <strong>Perhatian:</strong> belum ada tahun ajaran aktif. Tetapkan tahun ajaran aktif dan kapasitas kelas sebelum menerima siswa.
            @endif
        </div>
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['Semua pendaftar', $counts->sum(), ''],
                ['Menunggu seleksi', $counts['REGISTERED'] ?? 0, 'REGISTERED'],
                ['Lolos seleksi', $counts['PASSED'] ?? 0, 'PASSED'],
                ['Diterima', $counts['ACCEPTED'] ?? 0, 'ACCEPTED'],
            ] as [$label, $count, $filter])
                <button type="button" wire:click="$set('statusFilter', '{{ $filter }}')" class="rounded-2xl border px-4 py-3 text-left transition {{ $statusFilter === $filter ? 'border-white/60 bg-white/20' : 'border-white/15 bg-white/10 hover:bg-white/15' }}">
                    <span class="block text-xs font-medium text-violet-100">{{ $label }}</span>
                    <span class="mt-1 block text-2xl font-bold">{{ number_format($count) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div><h2 class="text-lg font-bold text-slate-900">Daftar calon siswa</h2><p class="mt-1 text-sm text-slate-500">Pendaftar yang diterima otomatis dibuat sebagai siswa dan dijadwalkan untuk sinkronisasi ke pusat.</p></div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <label class="sr-only" for="admission-search">Cari pendaftar</label>
                <input id="admission-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama, nomor, NISN..." class="rounded-xl border-slate-200 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                <label class="sr-only" for="admission-status">Filter status</label>
                <select id="admission-status" wire:model.live="statusFilter" class="rounded-xl border-slate-200 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                    <option value="">Semua status</option>
                    <option value="REGISTERED">Menunggu seleksi</option>
                    <option value="PASSED">Lolos seleksi</option>
                    <option value="ACCEPTED">Diterima</option>
                    <option value="REJECTED">Tidak lolos</option>
                </select>
                <select wire:model.live="perPage" aria-label="Jumlah pendaftar per halaman" class="rounded-xl border-slate-200 text-sm focus:border-indigo-400 focus:ring-indigo-200">
                    <option value="10">10 baris</option><option value="25">25 baris</option><option value="50">50 baris</option><option value="100">100 baris</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50/80">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3.5">Calon siswa</th><th class="px-5 py-3.5">Wali & kontak</th><th class="px-5 py-3.5">Kelas & kapasitas</th><th class="px-5 py-3.5">Tahun ajaran</th><th class="px-5 py-3.5">Status</th><th class="px-5 py-3.5 text-right">Proses</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $application)
                        @php
                            $statusLabel = ['REGISTERED' => 'Menunggu seleksi', 'PASSED' => 'Lolos seleksi', 'ACCEPTED' => 'Diterima', 'REJECTED' => 'Tidak lolos'][$application->status];
                            $statusColor = ['REGISTERED' => 'bg-amber-50 text-amber-800', 'PASSED' => 'bg-sky-50 text-sky-800', 'ACCEPTED' => 'bg-emerald-50 text-emerald-800', 'REJECTED' => 'bg-rose-50 text-rose-800'][$application->status];
                        @endphp
                        <tr wire:key="admission-{{ $application->id }}" class="align-top transition hover:bg-indigo-50/30">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $application->name }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold {{ $application->type === 'TRANSFER' ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-700' }}">{{ $application->type === 'TRANSFER' ? 'SISWA PINDAHAN' : 'PENDAFTARAN BARU' }}</span>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $application->registration_number }}@if($application->nisn) · NISN {{ $application->nisn }}@endif</p>
                                @if($application->birth_place || $application->birth_date)<p class="mt-1 text-xs text-slate-500">{{ $application->birth_place }}{{ $application->birth_place && $application->birth_date ? ', ' : '' }}{{ $application->birth_date?->format('d M Y') }}</p>@endif
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-slate-800">{{ $application->guardian_name }}</p>
                                <p class="text-xs text-slate-500">{{ $application->guardian_phone }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-sm font-semibold text-indigo-800">{{ $application->applied_class }}</span>
                                @php($availability = $classAvailability->get($application->applied_class))
                                @if($availability)
                                    @if($availability['capacity'] !== null)
                                        <p class="mt-2 text-xs font-medium {{ $availability['available'] > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $availability['enrolled'] }}/{{ $availability['capacity'] }} siswa · {{ $availability['available'] }} kursi tersedia</p>
                                    @else
                                        <p class="mt-2 text-xs font-semibold text-amber-700">Kapasitas belum diatur</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-xs font-semibold text-amber-700">Kapasitas belum diatur</p>
                                @endif
                                @if($application->previous_school)<p class="mt-1 text-xs text-slate-500">Asal: {{ $application->previous_school }}</p>@endif
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ $application->academicYear?->name ?? '—' }}<p class="mt-1 text-xs text-slate-400">{{ $application->created_at?->format('d M Y') }}</p></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusColor }}">{{ $statusLabel }}</span>@if($application->student_id)<p class="mt-1 text-xs text-emerald-700">Data siswa telah dibuat</p>@endif
                                @error('application-'.$application->id)<p class="mt-2 max-w-52 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if(!in_array($application->status, ['ACCEPTED', 'REJECTED'], true))
                                        <button type="button" wire:click="editApplication('{{ $application->id }}')" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</button>
                                    @endif
                                    @if($application->status === 'REGISTERED')
                                        @if($application->type === 'TRANSFER')
                                            @if($availability && $availability['available'] > 0)
                                                <button type="button" wire:click="advanceApplication('{{ $application->id }}', 'ACCEPTED')" wire:confirm="Terima siswa pindahan ini ke kelas tujuan?" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Terima pindahan</button>
                                            @else
                                                <span class="max-w-40 px-2 py-1 text-left text-xs font-medium text-rose-700">{{ $availability && $availability['capacity'] !== null ? 'Kelas penuh' : 'Atur kapasitas kelas dahulu' }}</span>
                                            @endif
                                        @else
                                            <button type="button" wire:click="advanceApplication('{{ $application->id }}', 'PASSED')" wire:confirm="Tandai calon ini lolos seleksi?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50">Lolos</button>
                                        @endif
                                        <button type="button" wire:click="advanceApplication('{{ $application->id }}', 'REJECTED')" wire:confirm="Tandai pendaftaran ini tidak lolos?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Tidak lolos</button>
                                    @elseif($application->status === 'PASSED')
                                        @if($availability && $availability['available'] > 0)
                                            <button type="button" wire:click="advanceApplication('{{ $application->id }}', 'ACCEPTED')" wire:confirm="Terima calon ini dan buat data siswa beserta kelasnya?" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Terima & buat siswa</button>
                                        @else
                                            <span class="max-w-40 px-2 py-1 text-left text-xs font-medium text-rose-700">{{ $availability && $availability['capacity'] !== null ? 'Kelas penuh' : 'Atur kapasitas kelas dahulu' }}</span>
                                        @endif
                                        <button type="button" wire:click="advanceApplication('{{ $application->id }}', 'REJECTED')" wire:confirm="Tandai pendaftaran ini tidak diterima?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Tidak diterima</button>
                                    @else
                                        @if($application->status === 'ACCEPTED' && $application->student_id)
                                            <a href="{{ route('student-documents', $application->student_id) }}" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Kelola dokumen</a>
                                        @else
                                            <span class="px-2.5 py-1.5 text-xs text-slate-400">Selesai</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center"><p class="font-semibold text-slate-800">Belum ada pendaftar</p><p class="mt-1 text-sm text-slate-500">Mulai dengan menambahkan pendaftaran siswa baru.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($applications->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $applications->links() }}</div>@endif
    </article>

    @if($showCapacityForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="capacity-form-title">
            <div class="my-auto max-h-[92vh] w-full max-w-3xl space-y-5 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Tahun ajaran {{ $activeYear?->name ?? 'belum diatur' }}</p><h2 id="capacity-form-title" class="mt-1 text-2xl font-bold text-slate-900">Kapasitas kelas</h2><p class="mt-1 text-sm text-slate-500">Atur jumlah maksimum siswa tiap kelas. Siswa yang sudah tercatat di kelas ikut dihitung.</p></div>
                    <button type="button" wire:click="$set('showCapacityForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                @if(!$activeYear)
                    <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Tahun ajaran aktif belum ditetapkan. Tetapkan pada pengaturan tahun ajaran terlebih dahulu.</div>
                @else
                    <div class="space-y-3">
                        @forelse($schoolClasses as $schoolClass)
                            @php($capacityInfo = $classAvailability->get($schoolClass->name))
                            <div wire:key="capacity-{{ $schoolClass->id }}" class="grid items-center gap-3 rounded-xl border border-slate-100 p-3 sm:grid-cols-[1fr_auto_auto]">
                                <div><p class="font-semibold text-slate-800">{{ $schoolClass->name }}</p><p class="text-xs text-slate-500">{{ $capacityInfo['enrolled'] }} siswa saat ini{{ $capacityInfo['capacity'] !== null ? ' · '.$capacityInfo['available'].' kursi tersisa' : ' · kapasitas belum diatur' }}</p></div>
                                <label class="text-xs font-medium text-slate-500">Maksimum siswa
                                    <input type="number" min="1" max="500" wire:model="capacityInputs.{{ $schoolClass->id }}" placeholder="1–500" class="mt-1 w-32 rounded-lg border-slate-200 text-sm">
                                </label>
                                <button type="button" wire:click="saveClassCapacity('{{ $schoolClass->id }}')" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Simpan</button>
                                @error('capacityInputs.'.$schoolClass->id)<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        @empty
                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada kelas di cabang ini. Tambahkan kelas beserta kapasitasnya di bawah.</p>
                        @endforelse
                    </div>
                    <div class="border-t border-slate-100 pt-4">
                        <h3 class="font-semibold text-slate-900">Tambah kelas dan kapasitas</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                            <label class="text-xs font-medium text-slate-500">Nama kelas
                                <input wire:model="capacityClassName" maxlength="50" placeholder="Contoh: Kelas 1B" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                @error('capacityClassName')<span class="mt-1 block text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="text-xs font-medium text-slate-500">Maksimum siswa
                                <input type="number" wire:model="capacityValue" min="1" max="500" placeholder="Contoh: 30" class="mt-1 w-36 rounded-lg border-slate-200 text-sm">
                                @error('capacityValue')<span class="mt-1 block text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <button type="button" wire:click="createClassCapacity" class="self-end rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Tambah kelas</button>
                        </div>
                    </div>
                @endif
                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showCapacityForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="admission-form-title">
            <form wire:submit="saveApplication" class="my-auto max-h-[92vh] w-full max-w-4xl space-y-5 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">{{ $formType === 'TRANSFER' ? 'Formulir siswa pindahan' : 'Formulir PPDB' }}</p><h2 id="admission-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Edit pendaftaran' : ($formType === 'TRANSFER' ? 'Terima siswa pindahan' : 'Pendaftaran calon siswa') }}</h2></div>
                    <button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold text-slate-700">Nama lengkap *
                        <input wire:model="formName" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" autocomplete="name">
                        @error('formName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">NISN
                        <input wire:model="formNisn" maxlength="30" class="mt-1.5 w-full rounded-xl border-slate-200" inputmode="numeric">
                        @error('formNisn')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Jenis kelamin
                        <select wire:model="formGender" class="mt-1.5 w-full rounded-xl border-slate-200"><option value="">Pilih</option><option value="LAKI_LAKI">Laki-laki</option><option value="PEREMPUAN">Perempuan</option></select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Tempat lahir
                        <input wire:model="formBirthPlace" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-200">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Tanggal lahir
                        <input wire:model="formBirthDate" type="date" max="{{ now()->toDateString() }}" class="mt-1.5 w-full rounded-xl border-slate-200">
                        @error('formBirthDate')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Tahun ajaran aktif
                        <input value="{{ $activeYear?->name ?? 'Belum ditetapkan' }}" disabled class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-slate-600">
                        @error('formAcademicYearId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Nama orang tua/wali *
                        <input wire:model="formGuardianName" required maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-200" autocomplete="name">
                        @error('formGuardianName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Nomor HP wali *
                        <input wire:model="formGuardianPhone" required maxlength="30" type="tel" class="mt-1.5 w-full rounded-xl border-slate-200" autocomplete="tel">
                        @error('formGuardianPhone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Asal sekolah{{ $formType === 'TRANSFER' ? ' *' : '' }}
                        <input wire:model="formPreviousSchool" maxlength="255" {{ $formType === 'TRANSFER' ? 'required' : '' }} class="mt-1.5 w-full rounded-xl border-slate-200">
                        @error('formPreviousSchool')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Kelas tujuan *
                        <select wire:model="formAppliedClass" required class="mt-1.5 w-full rounded-xl border-slate-200">
                            <option value="">Pilih kelas yang tersedia</option>
                            @foreach($classes as $class)
                                @php($classInfo = $classAvailability->get($class))
                                @if($classInfo && $classInfo['available'] > 0)
                                    <option value="{{ $class }}">{{ $class }} · {{ $classInfo['available'] }} kursi tersisa dari {{ $classInfo['capacity'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('formAppliedClass')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        @if($classes->isEmpty())<span class="mt-1 block text-xs text-amber-700">Tambahkan kelas dan atur kapasitas terlebih dahulu.</span>@endif
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">Alamat lengkap *
                        <textarea wire:model="formAddress" required maxlength="2000" rows="2" class="mt-1.5 w-full rounded-xl border-slate-200"></textarea>
                        @error('formAddress')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">Catatan admin
                        <textarea wire:model="formNotes" maxlength="2000" rows="2" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Catatan seleksi atau kelengkapan dokumen"></textarea>
                        @error('formNotes')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <p class="text-xs text-slate-500">NISN dapat dilengkapi saat pendaftaran. NISN wajib diisi sebelum calon siswa diterima. Kelas tujuan hanya menampilkan kelas dengan kapasitas tersedia.</p>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan pendaftaran</button>
                </div>
            </form>
        </div>
    @endif
</section>

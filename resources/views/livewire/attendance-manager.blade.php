<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Absensi dan Guru Pengganti</h1>
        <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->role === 'admin_pusat' ? 'Lihat catatan absensi dan penugasan guru pengganti lintas cabang.' : 'Tandai status guru dan pilih pengganti yang tidak memiliki jadwal atau penugasan lain pada jam tersebut.' }}</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(auth()->user()->role === 'admin_pusat')
        <label class="block max-w-sm rounded-xl bg-white p-5 text-sm font-medium shadow-sm">Cabang sekolah
            <select wire:model.live="branchId" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">Semua cabang</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
            </select>
        </label>
    @endif
    @if(auth()->user()->role === 'admin_cabang')
    <form wire:submit="saveAttendance" class="space-y-5 rounded-xl bg-white p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-medium">Tanggal
                <input type="date" wire:model.live="date" required class="mt-1 w-full rounded-lg border-slate-300">
            </label>
            <label class="text-sm font-medium">Guru
                <select wire:model.live="employeeId" required class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih guru</option>
                    @foreach($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }} · {{ $teacher->nip }}</option>@endforeach
                </select>
            </label>
            @error('employeeId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <label class="text-sm font-medium">Status
                <select wire:model.live="status" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="HADIR">Hadir</option>
                    <option value="IZIN">Izin (dipotong)</option>
                    <option value="ALFA">Alfa (dipotong)</option>
                    <option value="SAKIT">Sakit (tidak dipotong)</option>
                </select>
            </label>
        </div>
        @error('date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

        @if($status !== 'HADIR' && $employeeId)
            <div class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div>
                    <h2 class="font-semibold text-amber-950">Penugasan pengganti berdasarkan roster</h2>
                    <p class="mt-1 text-sm text-amber-900">Guru yang sudah mengajar, sudah ditugaskan di jam yang sama, atau tercatat tidak hadir tidak ditampilkan.</p>
                </div>
                @forelse($slots as $slot)
                    <label wire:key="substitution-{{ $slot->id }}" class="grid gap-2 text-sm font-medium md:grid-cols-[1fr_1.2fr] md:items-center">
                        <span>Jam {{ $slot->period }} · {{ $slot->subject }} · Kelas {{ $slot->class_name }} · Ruang {{ $slot->room }}</span>
                        <select wire:model="substituteAssignments.{{ $slot->id }}" class="rounded-lg border-slate-300">
                            <option value="">{{ $substitutesBySlot[$slot->id]->isEmpty() ? 'Tidak ada guru tersedia' : 'Pilih guru pengganti' }}</option>
                            @foreach($substitutesBySlot[$slot->id] as $substitute)
                                <option value="{{ $substitute->id }}">{{ $substitute->name }} · {{ $substitute->nip }}</option>
                            @endforeach
                        </select>
                        @error("substituteAssignments.{$slot->id}")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                @empty
                    <p class="text-sm text-amber-900">Guru ini tidak memiliki jadwal mengajar pada hari tersebut, jadi tidak ada slot pengganti yang perlu ditetapkan.</p>
                @endforelse
                @error('substituteAssignments')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif

        @if($status === 'SAKIT')
            <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Absensi sakit tercatat, tetapi tidak mengurangi gaji pokok. Pengganti tetap dapat ditugaskan untuk jam pelajaran terjadwal.</p>
        @elseif(in_array($status, ['IZIN', 'ALFA']))
            <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">Absensi {{ strtolower($status) }} mengurangi gaji harian berdasarkan gaji pokok dan jumlah hari kalender dalam bulan tersebut.</p>
        @endif

        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-5 py-2.5 font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan absensi</button>
    </form>
    @endif

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-semibold">Absensi tercatat · {{ $date }}</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($recentAttendances->total()) }} catatan absensi</p><div class="mt-2 flex gap-3"><a href="{{ route('reports.download', ['report' => 'attendance', 'branch_id' => $branchId ?: null, 'from' => $date, 'to' => $date, 'format' => 'pdf']) }}" class="text-xs font-semibold text-rose-700 hover:underline">Ekspor PDF</a><a href="{{ route('reports.download', ['report' => 'attendance', 'branch_id' => $branchId ?: null, 'from' => $date, 'to' => $date, 'format' => 'xlsx']) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Ekspor Excel</a></div></div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-600">Baris per halaman
                <select wire:model.live="perPage" aria-label="Jumlah absensi per halaman" class="rounded-lg border-slate-200 py-2 text-sm">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>@if(auth()->user()->role === 'admin_pusat')<th class="px-4 py-3">Cabang</th>@endif<th class="px-4 py-3">Guru</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Potongan / Detail pengganti</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentAttendances as $attendance)
                        <tr>
                            @if(auth()->user()->role === 'admin_pusat')<td class="px-4 py-3">{{ $attendance->employee?->branch?->name ?? '—' }}</td>@endif
                            <td class="px-4 py-3 font-medium">{{ $attendance->employee?->name ?? 'Guru terhapus' }}</td>
                            <td class="px-4 py-3">{{ ['HADIR' => 'Hadir', 'IZIN' => 'Izin', 'ALFA' => 'Alfa', 'SAKIT' => 'Sakit'][$attendance->status] ?? $attendance->status }}</td>
                            <td class="px-4 py-3">{{ $attendance->status === 'SAKIT' || $attendance->status === 'HADIR' ? 'Tidak ada' : 'Satu hari gaji pokok' }}
                                @foreach($substitutionsByEmployee->get($attendance->employee_id, collect()) as $substitution)
                                    <span class="block text-xs text-slate-500">Jam {{ $substitution->period }} · {{ $substitution->rosterSlot?->subject }} · {{ $substitution->substituteEmployee?->name }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada absensi untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentAttendances->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $recentAttendances->links() }}</div>
        @endif
    </div>
</section>

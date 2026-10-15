<section class="space-y-8">
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-indigo-800 to-violet-700 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-200">Dashboard yayasan</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Halo, {{ $user->name }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-indigo-100 sm:text-base">
                    @if($branch)
                        Ringkasan operasional {{ $branch->name }} · {{ $branch->level }}
                    @elseif($canViewAllBranches)
                        Pantau aktivitas administrasi dan keuangan seluruh cabang dari satu tempat.
                    @else
                        Ringkasan informasi sekolah Anda untuk hari ini.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold capitalize text-white">
                    {{ ['admin_pusat' => 'Admin Pusat', 'admin_cabang' => 'Admin Cabang', 'keuangan' => 'Keuangan', 'guru' => 'Guru', 'staff' => 'Staff'][$user->role] ?? $user->role }}
                </span>
                @if($activeAcademicYear)
                    <span class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-indigo-900">{{ $activeAcademicYear->name }}</span>
                @endif
            </div>
        </div>
        @if($canViewAllBranches)
            <div class="mt-6 max-w-sm">
                <label for="dashboard-branch" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-indigo-200">Filter cabang</label>
                <select id="dashboard-branch" wire:model.live="branchId" class="w-full rounded-xl border-0 bg-white/95 px-4 py-3 text-sm font-medium text-slate-800 shadow-sm focus:ring-2 focus:ring-white">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->level }}</option>@endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if($canManageSchool)
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">Total siswa</p>
                    <span class="rounded-xl bg-sky-50 p-2.5 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                </div>
                <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($studentCount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Siswa terdaftar {{ $branch ? 'di cabang' : 'di yayasan' }}</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">Total pegawai</p>
                    <span class="rounded-xl bg-violet-50 p-2.5 text-violet-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/></svg></span>
                </div>
                <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($employeeCount) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($teacherCount) }} guru · pegawai dan staff</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">Hadir hari ini</p>
                    <span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/></svg></span>
                </div>
                <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format((int) ($attendanceCounts->present ?? 0)) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format((int) ($attendanceCounts->absent ?? 0)) }} tercatat izin, alfa, atau sakit</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">Jadwal pelajaran</p>
                    <span class="rounded-xl bg-amber-50 p-2.5 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h3M8 17h7"/></svg></span>
                </div>
                <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($rosterCount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Slot tersusun pada tahun ajaran aktif</p>
            </article>
        @elseif($canViewFinance)
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Tagihan SPP</p>
                <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900">{{ number_format((int) ($sppSummary->total ?? 0)) }}</p>
                <p class="mt-1 text-xs text-slate-500">Tahun ajaran {{ $activeAcademicYear?->name ?? 'aktif' }}</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">SPP belum lunas</p>
                <p class="mt-5 text-3xl font-bold tracking-tight text-amber-600">{{ number_format((int) ($sppSummary->unpaid ?? 0)) }}</p>
                <p class="mt-1 text-xs text-slate-500">Perlu ditindaklanjuti</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">SPP terkumpul</p>
                <p class="mt-5 text-2xl font-bold tracking-tight text-emerald-700">Rp {{ number_format((float) ($sppSummary->collected ?? 0), 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Dari tagihan yang sudah lunas</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Payroll menunggu</p>
                <p class="mt-5 text-3xl font-bold tracking-tight text-indigo-700">{{ number_format((int) ($payrollSummary->pending ?? 0)) }}</p>
                <p class="mt-1 text-xs text-slate-500">Periode {{ $payrollPeriod }}</p>
            </article>
        @else
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Siswa di sekolah</p><p class="mt-5 text-3xl font-bold text-slate-900">{{ number_format($studentCount) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Guru</p><p class="mt-5 text-3xl font-bold text-slate-900">{{ number_format($teacherCount) }}</p>
            </article>
        @endif
    </div>

    @if($canViewFinance)
        <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-lg font-bold text-slate-900">Status pembayaran SPP</h2><p class="mt-1 text-sm text-slate-500">Ringkasan lunas dan belum lunas per bulan · {{ $activeAcademicYear?->name ?? 'Semua tahun' }}</p></div>
                    <a href="{{ route('spp') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Lihat rincian</a>
                </div>
                <div class="mt-6 flex h-56 items-end gap-2 border-b border-slate-100 pb-0 sm:gap-3">
                    @foreach($sppChart as $month)
                        @php($total = $month['paid'] + $month['unpaid'])
                        <div class="group flex h-full min-w-0 flex-1 flex-col justify-end">
                            <div class="relative flex h-[190px] items-end justify-center gap-1">
                                @if($total > 0)
                                    <div title="Lunas: Rp {{ number_format($month['paid'], 0, ',', '.') }}" class="w-2/5 max-w-7 rounded-t-md bg-emerald-500 transition-all group-hover:bg-emerald-600" style="height: {{ max(5, $month['paid'] / $sppMax * 100) }}%"></div>
                                    <div title="Belum lunas: Rp {{ number_format($month['unpaid'], 0, ',', '.') }}" class="w-2/5 max-w-7 rounded-t-md bg-amber-300 transition-all group-hover:bg-amber-400" style="height: {{ max(5, $month['unpaid'] / $sppMax * 100) }}%"></div>
                                @else
                                    <div class="w-2/5 max-w-7 rounded-t-md bg-slate-100" style="height: 4px"></div>
                                @endif
                            </div>
                            <span class="py-2 text-center text-[10px] text-slate-500 sm:text-xs">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-5 text-xs text-slate-500"><span class="flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Lunas</span><span class="flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-sm bg-amber-300"></i>Belum lunas</span></div>
            </article>

            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-lg font-bold text-slate-900">Ringkasan payroll</h2><p class="mt-1 text-sm text-slate-500">Periode {{ $payrollPeriod }}</p></div>
                    <a href="{{ route('payroll') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Kelola</a>
                </div>
                <div class="mt-7 space-y-5">
                    <div><div class="mb-2 flex justify-between text-sm"><span class="text-slate-500">Sudah dibayar</span><strong class="text-slate-800">{{ number_format((int) ($payrollSummary->paid ?? 0)) }} pegawai</strong></div><p class="text-lg font-bold text-emerald-700">Rp {{ number_format((float) ($payrollSummary->paid_amount ?? 0), 0, ',', '.') }}</p></div>
                    <div><div class="mb-2 flex justify-between text-sm"><span class="text-slate-500">Menunggu pembayaran</span><strong class="text-slate-800">{{ number_format((int) ($payrollSummary->pending ?? 0)) }} pegawai</strong></div><p class="text-lg font-bold text-amber-600">Rp {{ number_format((float) ($payrollSummary->pending_amount ?? 0), 0, ',', '.') }}</p></div>
                </div>
                <div class="mt-8 border-t border-slate-100 pt-5"><p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Tren total payroll 6 bulan</p>
                    <div class="flex h-20 items-end gap-2">
                        @foreach($payrollChart as $month)
                            <div class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                                <div title="{{ $month['label'] }} · Rp {{ number_format($month['total'], 0, ',', '.') }}" class="w-full max-w-8 rounded-t-md bg-indigo-400 transition-colors group-hover:bg-indigo-600" style="height: {{ $month['total'] > 0 ? max(6, $month['total'] / $payrollMax * 100) : 4 }}%"></div>
                                <span class="text-[10px] text-slate-400">{{ ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'][$month['label']] ?? $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    @endif

    @if($canManageSchool && $branchSummary->isNotEmpty())
        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-900">Cabang yayasan</h2><p class="mt-1 text-sm text-slate-500">Ringkasan siswa dan pegawai per sekolah</p></div><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $branchSummary->count() }} cabang ditampilkan</span></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($branchSummary as $item)
                    <button type="button" wire:click="$set('branchId', '{{ $item->id }}')" class="rounded-xl border border-slate-100 p-4 text-left transition hover:border-indigo-200 hover:bg-indigo-50/50">
                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">{{ $item->level }}</span>
                        <span class="mt-1 block truncate font-semibold text-slate-900">{{ $item->name }}</span>
                        <span class="mt-3 block text-xs text-slate-500">{{ number_format($item->students_count) }} siswa · {{ number_format($item->employees_count) }} pegawai</span>
                    </button>
                @endforeach
            </div>
        </article>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @if($canManageSchool)
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-900">Absensi terbaru</h2><p class="mt-1 text-sm text-slate-500">Catatan izin, alfa, dan sakit bulan ini</p></div><a href="{{ route('attendance') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Buka absensi →</a></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recentAbsences as $attendance)
                        <div class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $attendance->employee?->name ?? 'Pegawai' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $attendance->date->format('d M Y') }}</p></div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $attendance->status === 'SAKIT' ? 'bg-sky-50 text-sky-700' : ($attendance->status === 'IZIN' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ ['IZIN' => 'Izin', 'ALFA' => 'Alfa', 'SAKIT' => 'Sakit'][$attendance->status] ?? $attendance->status }}</span>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">Belum ada catatan absensi pada bulan ini.</p>
                    @endforelse
                </div>
            </article>
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">Akses cepat</h2><p class="mt-1 text-sm text-slate-500">Pintasan ke pekerjaan yang sering digunakan</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('attendance') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Kelola absensi</span><span class="mt-1 block text-xs text-slate-500">Catat kehadiran dan atur guru pengganti</span></a>
                    <a href="{{ route('roster') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Lihat roster</span><span class="mt-1 block text-xs text-slate-500">Jadwal pelajaran per tahun ajaran</span></a>
                    @if($canViewFinance)
                        <a href="{{ route('payroll') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Penggajian</span><span class="mt-1 block text-xs text-slate-500">Proses payroll dan insentif</span></a>
                        <a href="{{ route('spp') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Pemantauan SPP</span><span class="mt-1 block text-xs text-slate-500">Periksa tagihan dan pembayaran</span></a>
                    @endif
                </div>
            </article>
        @elseif($canViewFinance)
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-900">Akses cepat keuangan</h2><p class="mt-1 text-sm text-slate-500">Pilih pekerjaan keuangan yang ingin dilanjutkan.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('spp') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Kelola dan pantau SPP</span><span class="mt-1 block text-xs text-slate-500">Lihat tagihan siswa dan status pembayaran</span></a>
                    <a href="{{ route('payroll') }}" class="rounded-xl border border-slate-100 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50"><span class="font-semibold text-slate-900">Kelola penggajian</span><span class="mt-1 block text-xs text-slate-500">Hitung insentif dan proses gaji pegawai</span></a>
                </div>
            </article>
        @else
            <article class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-900">Informasi sekolah</h2><p class="mt-2 text-sm leading-6 text-slate-500">Gunakan menu roster untuk melihat jadwal pelajaran tahun ajaran aktif. Hubungi administrator cabang untuk bantuan administrasi.</p>
                <a href="{{ route('roster') }}" class="mt-4 inline-flex rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Buka roster pelajaran</a>
            </article>
        @endif
    </div>
</section>

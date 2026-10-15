<section class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Ringkasan operasional dan keuangan</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Laporan cabang</h1>
        <p class="mt-2 text-sm text-slate-500">Pantau data sekolah, absensi, penggajian, dan penerimaan SPP sesuai kewenangan akun.</p>
        @if(auth()->user()->branch?->address)
            <p class="mt-1 text-xs text-slate-400">{{ auth()->user()->branch->address }}</p>
        @endif
    </div>

    <section class="grid gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:grid-cols-3">
        @if($isCentralAdmin)
            <label class="text-sm font-semibold text-slate-700">Cabang
                <select wire:model.live="branchId" class="mt-1.5 w-full rounded-xl border-slate-200">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} · {{ $branch->level }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <div class="text-sm font-semibold text-slate-700">Cabang
                <div class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 font-normal">{{ auth()->user()->branch?->name ?? 'Cabang pengguna' }}</div>
            </div>
        @endif
        <label class="text-sm font-semibold text-slate-700">Periode laporan
            <input type="month" wire:model.live="period" class="mt-1.5 w-full rounded-xl border-slate-200">
        </label>
        @if($canViewFinance)
            <label class="text-sm font-semibold text-slate-700">Tahun ajaran SPP
                <select wire:model.live="academicYearId" class="mt-1.5 w-full rounded-xl border-slate-200">
                    <option value="">Tidak ada tahun ajaran</option>
                    @foreach($academicYears as $year)
                        <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_active ? ' · Aktif' : '' }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </section>

    @if($canViewSchool)
        <section>
            <div class="mb-3 flex items-end justify-between gap-3">
                <div><h2 class="text-lg font-bold text-slate-900">Operasional sekolah</h2><p class="text-sm text-slate-500">Absensi untuk {{ $periodDate->locale('id')->translatedFormat('F Y') }}</p></div>
                <div class="flex gap-3"><a href="{{ route('reports.download', ['report' => 'students', 'branch_id' => $branchId ?: null, 'format' => 'pdf']) }}" class="text-sm font-semibold text-rose-700 hover:underline">Siswa PDF</a><a href="{{ route('reports.download', ['report' => 'students', 'branch_id' => $branchId ?: null, 'format' => 'xlsx']) }}" class="text-sm font-semibold text-emerald-700 hover:underline">Siswa Excel</a></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Siswa terdaftar</p><p class="mt-3 text-3xl font-bold">{{ number_format($school['students']) }}</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Guru dan staff</p><p class="mt-3 text-3xl font-bold">{{ number_format($school['employees']) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($school['teachers']) }} guru · {{ number_format($school['staff']) }} staff</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Hadir tercatat</p><p class="mt-3 text-3xl font-bold text-emerald-700">{{ number_format($school['present']) }}</p><p class="mt-1 text-xs text-slate-500">Total {{ number_format($school['attendance_total']) }} catatan absensi</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Tidak hadir</p><p class="mt-3 text-3xl font-bold text-rose-700">{{ number_format($school['absent']) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($school['permission']) }} izin · {{ number_format($school['sick']) }} sakit</p></article>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('reports.download', ['report' => 'employees', 'branch_id' => $branchId ?: null]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ekspor guru & pegawai</a>
                <a href="{{ route('reports.download', ['report' => 'attendance', 'branch_id' => $branchId ?: null, 'from' => $periodDate->copy()->startOfMonth()->toDateString(), 'to' => $periodDate->copy()->endOfMonth()->toDateString()]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ekspor absensi periode ini</a>
            </div>
        </section>
    @endif

    @if($canViewFinance)
        <section>
            <div class="mb-3 flex items-end justify-between gap-3">
                <div><h2 class="text-lg font-bold text-slate-900">Keuangan</h2><p class="text-sm text-slate-500">Payroll {{ $periodDate->locale('id')->translatedFormat('F Y') }} · SPP tahun ajaran {{ $academicYears->firstWhere('id', $academicYearId)?->name ?? '—' }}</p></div>
                @if(auth()->user()->role === 'keuangan_pusat')
                    <div class="flex gap-3"><a href="{{ route('reports.download', ['report' => 'payroll', 'period' => $period, 'branch_id' => $branchId ?: null, 'format' => 'pdf']) }}" class="text-sm font-semibold text-rose-700 hover:underline">Payroll PDF</a><a href="{{ route('payroll.export.xlsx', ['period' => $period, 'branch_id' => $branchId ?: null]) }}" class="text-sm font-semibold text-emerald-700 hover:underline">Payroll Excel</a></div>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Payroll dibayar</p><p class="mt-3 text-2xl font-bold text-emerald-700">Rp {{ number_format($finance['payroll_paid_amount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($finance['payroll_paid_count']) }} pegawai dari {{ number_format($finance['payroll_total']) }}</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Payroll menunggu</p><p class="mt-3 text-2xl font-bold text-amber-600">Rp {{ number_format($finance['payroll_pending_amount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($finance['payroll_pending_count']) }} payroll pending</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Penerimaan SPP</p><p class="mt-3 text-2xl font-bold text-indigo-700">Rp {{ number_format($finance['spp_collected'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($finance['spp_paid_count']) }} dari {{ number_format($finance['spp_total']) }} tagihan lunas</p></article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">SPP belum tertagih</p><p class="mt-3 text-2xl font-bold text-rose-700">Rp {{ number_format($finance['spp_outstanding'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($finance['spp_unpaid_count']) }} tagihan berstatus belum lunas</p></article>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('reports.download', ['report' => 'spp', 'branch_id' => $branchId ?: null, 'format' => 'pdf']) }}" class="rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Ekspor SPP PDF</a>
                <a href="{{ route('reports.download', ['report' => 'spp', 'branch_id' => $branchId ?: null, 'format' => 'xlsx']) }}" class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Ekspor SPP Excel</a>
                <a href="{{ route('payroll.slips.pdf', ['period' => $period, 'branch_id' => $branchId ?: null]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Unduh slip gaji PDF</a>
                <a href="{{ route('payroll.export.xlsx', ['period' => $period, 'branch_id' => $branchId ?: null]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ekspor payroll Excel</a>
            </div>
        </section>
    @endif

    @if($isCentralAdmin && $branchSummaries->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Ikhtisar seluruh cabang</h2><p class="mt-1 text-sm text-slate-500">Ringkasan sekolah dan keuangan berdasarkan filter periode terpilih.</p><div class="mt-2 flex gap-3"><a href="{{ route('reports.download', ['report' => 'branch-summary', 'period' => $period, 'academic_year_id' => $academicYearId, 'from' => $periodDate->copy()->startOfMonth()->toDateString(), 'to' => $periodDate->copy()->endOfMonth()->toDateString(), 'format' => 'pdf']) }}" class="text-xs font-semibold text-rose-700 hover:underline">Ekspor ikhtisar PDF</a><a href="{{ route('reports.download', ['report' => 'branch-summary', 'period' => $period, 'academic_year_id' => $academicYearId, 'from' => $periodDate->copy()->startOfMonth()->toDateString(), 'to' => $periodDate->copy()->endOfMonth()->toDateString(), 'format' => 'xlsx']) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Ekspor ikhtisar Excel</a></div></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Cabang</th>@if($canViewSchool)<th class="px-4 py-3">Siswa</th><th class="px-4 py-3">Pegawai</th><th class="px-4 py-3">Hadir / tidak hadir</th>@endif @if($canViewFinance)<th class="px-4 py-3">Payroll dibayar / menunggu</th><th class="px-4 py-3">SPP terkumpul / sisa</th>@endif</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($branchSummaries as $summary)
                            <tr wire:key="report-branch-{{ $summary['id'] }}">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $summary['name'] }}<span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $summary['level'] }}</span></td>
                                @if($canViewSchool)<td class="px-4 py-3">{{ number_format($summary['students']) }}</td><td class="px-4 py-3">{{ number_format($summary['employees']) }}</td><td class="px-4 py-3">{{ number_format($summary['present']) }} / {{ number_format($summary['absent']) }}</td>@endif
                                @if($canViewFinance)<td class="px-4 py-3"><span class="text-emerald-700">Rp {{ number_format($summary['payroll_paid'], 0, ',', '.') }}</span><br><span class="text-xs text-amber-700">Menunggu Rp {{ number_format($summary['payroll_pending'], 0, ',', '.') }}</span></td><td class="px-4 py-3"><span class="text-indigo-700">Rp {{ number_format($summary['spp_collected'], 0, ',', '.') }}</span><br><span class="text-xs text-rose-700">Sisa Rp {{ number_format($summary['spp_outstanding'], 0, ',', '.') }}</span></td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</section>

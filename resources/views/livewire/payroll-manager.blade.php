<section class="space-y-6">
    <div>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Manajemen Penggajian</h1>
                <p class="mt-1 text-sm text-slate-500">Hitung gaji, tinjau tunjangan otomatis, dan proses pembayaran payroll.</p>
            </div>
            <a href="{{ route('incentives') }}" class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Pengaturan insentif
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        <form wire:submit="savePayroll" class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-slate-900">Hitung payroll</h2>
            @if(auth()->user()->role === 'admin_pusat')
                <label class="block text-sm font-medium">Cabang
                    <select wire:model.live="branchId" required class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">Pilih cabang</option>
                        @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                    </select>
                </label>
                @error('branchId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @endif
            <label class="block text-sm font-medium">Pegawai
                <select wire:model.live="employeeId" required class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih pegawai</option>
                    @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->nip }}</option>@endforeach
                </select>
            </label>
            @error('employeeId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <label class="block text-sm font-medium">Periode
                <input type="month" wire:model.live="period" required class="mt-1 w-full rounded-lg border-slate-300">
            </label>
            @error('period')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <label class="block text-sm font-medium">Tahun ajaran
                <select wire:model.live="academicYearId" required class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih tahun ajaran</option>
                    @foreach($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach
                </select>
            </label>
            @error('academicYearId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Insentif manual untuk payroll ini</h3>
                    <p class="mt-1 text-xs text-slate-500">Masukkan nilai insentif periode ini. Jenis insentif tetap dan konfigurasi cabang dikelola terpisah.</p>
                </div>
                @forelse($manualIncentiveTypes as $incentive)
                    <label wire:key="payroll-incentive-{{ $incentive->id }}" class="block text-sm font-medium">
                        {{ $incentive->name }}
                        <span class="font-normal text-slate-400">(nilai awal Rp {{ number_format((float) $incentive->default_amount, 0, ',', '.') }})</span>
                        <input type="number" min="0" step="0.01"
                            wire:model="incentiveAmounts.{{ $incentive->id }}"
                            class="mt-1 w-full rounded-lg border-slate-300">
                    </label>
                    @error("incentiveAmounts.{$incentive->id}")<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @empty
                    <p class="text-sm text-slate-500">Belum ada jenis insentif manual pada cabang ini.</p>
                @endforelse
                @error('incentiveAmounts')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="block text-sm font-medium">Tunjangan (Rp)
                <span class="block text-xs font-normal text-slate-500">Tunjangan tambahan manual di luar transport dan invalen otomatis.</span>
                <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="allowances" required class="mt-1 w-full rounded-lg border-slate-300">
            </label>
            @error('allowances')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs text-slate-500">Potongan alfa otomatis dihitung per hari berdasarkan jumlah hari kalender pada periode tersebut.</p>

            @if($payrollPreview)
                <section class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <h3 class="font-semibold text-emerald-950">Pratinjau total payroll · {{ $previewEmployee->name }}</h3>
                    <div class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                        <p>Gaji pokok <span class="float-right font-medium">Rp {{ number_format($payrollPreview['base_salary'], 0, ',', '.') }}</span></p>
                        <p>Tambahan gaji jabatan <span class="float-right font-medium">Rp {{ number_format($payrollPreview['position_salary'], 0, ',', '.') }}</span></p>
                        <p>Total insentif <span class="float-right font-medium">Rp {{ number_format($payrollPreview['total_incentives'], 0, ',', '.') }}</span></p>
                        <p>Transport <span class="float-right font-medium">Rp {{ number_format($payrollPreview['allowance_details'][0]['amount'], 0, ',', '.') }}</span></p>
                        <p>Invalen ({{ $payrollPreview['substitution_count'] }} jam) <span class="float-right font-medium">Rp {{ number_format($payrollPreview['allowance_details'][1]['amount'], 0, ',', '.') }}</span></p>
                        <p>Tunjangan manual <span class="float-right font-medium">Rp {{ number_format((float) $allowances, 0, ',', '.') }}</span></p>
                        @foreach($payrollPreview['position_details'] as $positionDetail)
                            <p>{{ $positionDetail['name'] }} · tunjangan <span class="float-right font-medium">Rp {{ number_format($positionDetail['allowance'], 0, ',', '.') }}</span></p>
                        @endforeach
                        <p>Potongan absensi <span class="float-right font-medium text-rose-700">− Rp {{ number_format($payrollPreview['deductions'], 0, ',', '.') }}</span></p>
                    </div>
                    <div class="flex justify-between border-t border-emerald-200 pt-2 text-base font-bold text-emerald-950">
                        <span>Gaji bersih otomatis</span>
                        <span>Rp {{ number_format($payrollPreview['net_salary'], 0, ',', '.') }}</span>
                    </div>
                </section>
            @endif
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white hover:bg-indigo-700">Simpan perhitungan</button>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-900">Daftar gaji · {{ $period }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($salaries->total()) }} data payroll</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                        <a href="{{ route('reports.download', ['report' => 'payroll', 'period' => $period, 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 hover:underline">Ekspor laporan CSV</a>
                        <a href="{{ route('payroll.export.xlsx', ['period' => $period, 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:underline">Ekspor Excel (.xlsx)</a>
                        <a href="{{ route('payroll.slips.pdf', ['period' => $period, 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 hover:underline">Unduh slip gaji PDF (2 per lembar)</a>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">Baris per halaman
                    <select wire:model.live="perPage" aria-label="Jumlah payroll per halaman" class="rounded-lg border-slate-200 py-2 text-sm">
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-3">Pegawai</th><th class="px-4 py-3">Total insentif</th><th class="px-4 py-3">Total tunjangan</th><th class="px-4 py-3">Potongan</th><th class="px-4 py-3">Gaji bersih</th><th class="px-4 py-3">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($salaries as $salary)
                            <tr>
                                <td class="px-4 py-3 font-medium">
                                    <div>{{ $salary->employee?->name ?? 'Pegawai terhapus' }}</div>
                                    @if((float) $salary->position_salary > 0)
                                        <div class="mt-1 text-xs font-medium text-indigo-700">Tambahan gaji jabatan: Rp {{ number_format((float) $salary->position_salary, 0, ',', '.') }}</div>
                                        <div class="text-xs text-slate-500">{{ collect($salary->position_details ?? [])->pluck('name')->filter()->unique()->implode(', ') }}</div>
                                    @endif
                                    @if($salary->employee)
                                        <a href="{{ route('payroll.slips.pdf', ['period' => $period, 'employee_id' => $salary->employee_id]) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-indigo-700 hover:underline">Cetak slip pegawai</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    Rp {{ number_format((float) $salary->incentives, 0, ',', '.') }}
                                    @if($salary->incentive_details)
                                        <details class="mt-1 text-xs text-slate-500">
                                            <summary class="cursor-pointer">Rincian insentif</summary>
                                            <ul class="mt-1 space-y-1">
                                                @foreach($salary->incentive_details as $detail)
                                                                            <li>{{ match($detail['type'] ?? 'MANUAL') { 'ANNUAL' => 'Tahunan', 'POSITION' => 'Jabatan', default => 'Manual' } }} · {{ $detail['name'] ?? 'Insentif' }}: Rp {{ number_format((float) ($detail['amount'] ?? 0), 0, ',', '.') }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    Rp {{ number_format((float) $salary->allowances, 0, ',', '.') }}
                                    @if($salary->allowance_details)
                                        <details class="mt-1 text-xs text-slate-500">
                                            <summary class="cursor-pointer">Rincian tunjangan</summary>
                                            <ul class="mt-1 space-y-1">
                                                @foreach($salary->allowance_details as $detail)
                                                    <li>{{ $detail['name'] ?? 'Tunjangan' }}@if(isset($detail['count'])) ({{ $detail['count'] }} jam × Rp {{ number_format((float) ($detail['rate'] ?? 0), 0, ',', '.') }})@endif: Rp {{ number_format((float) ($detail['amount'] ?? 0), 0, ',', '.') }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-4 py-3">Rp {{ number_format((float) $salary->deductions, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">Rp {{ number_format((float) $salary->net_salary, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if($salary->status === 'PENDING')
                                        <button wire:click="markPaid('{{ $salary->id }}')" wire:confirm="Tandai penggajian ini sudah dibayar?" class="text-indigo-600 hover:underline">Tandai dibayar</button>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dibayar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada data penggajian untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($salaries->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">{{ $salaries->links() }}</div>
            @endif
        </div>
    </div>
</section>

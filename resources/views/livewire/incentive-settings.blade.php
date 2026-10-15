<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Insentif & tunjangan</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola jenis insentif, nilai tetap tahunan, serta tarif tunjangan otomatis secara terpisah dari proses payroll.</p>
        </div>
        <a href="{{ route('payroll') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
            Kembali ke penggajian
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true))
        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <label class="block max-w-xl text-sm font-semibold text-slate-700">Cabang sekolah
                <select wire:model.live="branchId" class="mt-1.5 w-full rounded-xl border-slate-200">
                    <option value="">Pilih cabang untuk mengatur insentif</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
            @error('branchId')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
        </section>
    @endif

    @if($branchId)
        @if(auth()->user()->role === 'admin_pusat')
            <article class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="font-semibold text-slate-900">Tarif gaji pokok guru per jam</h2>
                    <p class="mt-1 text-sm text-slate-500">Tarif berlaku untuk cabang dan tahun ajaran terpilih. Gaji pokok bulanan guru dihitung dari tarif ini dikalikan jam mengajar pada roster yang jatuh dalam bulan payroll.</p>
                </div>
                <label class="block max-w-xl text-sm font-medium text-slate-700">Tahun ajaran
                    <select wire:model.live="academicYearId" class="mt-1.5 w-full rounded-xl border-slate-200">
                        <option value="">Pilih tahun ajaran</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                        @endforeach
                    </select>
                    @error('academicYearId')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror
                </label>
                <form wire:submit="saveTeacherHourlyRate" class="flex flex-wrap items-end gap-3">
                    <label class="min-w-64 flex-1 text-sm font-medium text-slate-700">Tarif per jam mengajar (Rp)
                        <input type="number" min="0" step="0.01" wire:model="teacherHourlyRate" required class="mt-1.5 w-full rounded-xl border-slate-200">
                        @error('teacherHourlyRate')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Simpan tarif guru</button>
                </form>
            </article>
        @endif

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="font-semibold text-slate-900">Tarif otomatis cabang</h2>
                    <p class="mt-1 text-sm text-slate-500">Nilai transport berlaku per bulan untuk pegawai. Tarif invalen dikalikan jumlah jam pengganti guru yang tercatat pada periode payroll.</p>
                </div>
                <label class="block text-sm font-medium text-slate-700">Transport bulanan per pegawai (Rp)
                    <input type="number" min="0" step="0.01" wire:model="monthlyTransportAllowance" @disabled(auth()->user()->role !== 'admin_pusat') class="mt-1.5 w-full rounded-xl border-slate-200">
                    @error('monthlyTransportAllowance')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-medium text-slate-700">Tarif invalen per jam pengganti (Rp)
                    <input type="number" min="0" step="0.01" wire:model="substitutionAllowancePerPeriod" @disabled(auth()->user()->role !== 'admin_pusat') class="mt-1.5 w-full rounded-xl border-slate-200">
                    @error('substitutionAllowancePerPeriod')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror
                </label>
                @if(auth()->user()->role === 'admin_pusat')
                    <button type="button" wire:click="saveBranchPayrollSettings" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Simpan tarif cabang</button>
                @endif
            </article>

            @if(auth()->user()->role === 'admin_pusat')
            <article class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="font-semibold text-slate-900">Tambah jenis insentif</h2>
                    <p class="mt-1 text-sm text-slate-500">Insentif tahunan diatur untuk pegawai per tahun ajaran; insentif manual dimasukkan saat menghitung gaji.</p>
                </div>
                <form wire:submit="addIncentiveType" class="space-y-3">
                    <label class="block text-sm font-medium text-slate-700">Nama insentif
                        <input type="text" wire:model="newIncentiveName" maxlength="100" required placeholder="Contoh: Wali kelas" class="mt-1.5 w-full rounded-xl border-slate-200">
                    </label>
                    @error('newIncentiveName')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                    <label class="block text-sm font-medium text-slate-700">Cara menghitung
                        <select wire:model.live="newIncentiveType" class="mt-1.5 w-full rounded-xl border-slate-200">
                            <option value="ANNUAL">Nilai tetap per pegawai/tahun ajaran</option>
                            <option value="MANUAL">Nilai manual saat payroll</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Nilai awal (Rp)
                        <input type="number" wire:model="newIncentiveDefault" min="0" step="0.01" required class="mt-1.5 w-full rounded-xl border-slate-200">
                        <span class="mt-1 block text-xs font-normal text-slate-500">Untuk insentif tahunan, nilai ini hanya nilai awal; nominal masing-masing pegawai diatur pada bagian nilai tahunan.</span>
                    </label>
                    @error('newIncentiveDefault')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                    <button type="submit" class="rounded-xl border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Tambah jenis insentif</button>
                </form>
            </article>
            @endif
        </section>

        <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold text-slate-900">Daftar jenis insentif cabang</h2>
                <p class="mt-1 text-sm text-slate-500">Nonaktifkan jenis yang tidak lagi digunakan; rincian payroll lama tetap tersimpan.</p>
            </div>
            @if($incentives->isNotEmpty())
                <div class="divide-y divide-slate-100">
                    @foreach($incentives as $incentive)
                        @if(auth()->user()->role === 'admin_pusat')
                        <form wire:key="incentive-{{ $incentive->id }}" wire:submit="saveIncentiveType('{{ $incentive->id }}')" class="grid gap-3 p-4 md:grid-cols-[1fr_1fr_1fr_auto_auto] md:items-end">
                            <label class="text-xs font-semibold text-slate-500">Nama insentif
                                <input type="text" wire:model="incentiveDefinitions.{{ $incentive->id }}.name" maxlength="100" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                @error("incentiveDefinitions.{$incentive->id}.name")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="text-xs font-semibold text-slate-500">Metode
                                <select wire:model="incentiveDefinitions.{{ $incentive->id }}.calculation_type" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                    <option value="ANNUAL">Tetap tahunan</option>
                                    <option value="MANUAL">Manual saat payroll</option>
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-slate-500">Nilai awal (Rp)
                                <input type="number" wire:model="incentiveDefinitions.{{ $incentive->id }}.default_amount" min="0" step="0.01" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            </label>
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Simpan</button>
                            <button type="button" wire:click="toggleIncentiveType('{{ $incentive->id }}')" wire:confirm="{{ $incentive->is_active ? 'Nonaktifkan jenis insentif ini?' : 'Aktifkan kembali jenis insentif ini?' }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ $incentive->is_active ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50' }}">{{ $incentive->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <div class="text-xs md:col-span-5">
                                <span class="rounded-full px-2.5 py-1 font-semibold {{ $incentive->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $incentive->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                        </form>
                        @else
                        <div wire:key="incentive-{{ $incentive->id }}" class="grid gap-3 p-4 md:grid-cols-3 md:items-center">
                            <span class="text-sm font-semibold text-slate-800">{{ $incentive->name }}</span>
                            <span class="text-sm text-slate-600">{{ $incentive->calculation_type === 'ANNUAL' ? 'Tetap tahunan' : 'Manual saat payroll' }}</span>
                            <span class="text-sm text-slate-600">Nilai awal: Rp {{ number_format((float) $incentive->default_amount, 0, ',', '.') }}</span>
                            <span class="text-xs text-slate-500">{{ $incentive->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada konfigurasi insentif untuk cabang ini.</p>
            @endif
        </article>

        @if(auth()->user()->role === 'keuangan_pusat')
        <article class="space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div>
                <h2 class="font-semibold text-slate-900">Insentif tetap per pegawai dan tahun ajaran</h2>
                <p class="mt-1 text-sm text-slate-500">Nilai yang disimpan di sini otomatis menjadi bagian perhitungan payroll ketika tahun ajaran yang sama dipilih.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Pegawai
                    <select wire:model.live="employeeId" class="mt-1.5 w-full rounded-xl border-slate-200">
                        <option value="">Pilih pegawai</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->nip }} ({{ $employee->type }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Tahun ajaran
                    <select wire:model.live="academicYearId" class="mt-1.5 w-full rounded-xl border-slate-200">
                        <option value="">Pilih tahun ajaran</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @if($annualIncentives->isNotEmpty())
                @if($employeeId && $academicYearId)
                    <form wire:submit="saveAnnualIncentives" class="space-y-4 border-t border-slate-100 pt-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach($annualIncentives as $incentive)
                                <label wire:key="annual-incentive-{{ $incentive->id }}" class="text-sm font-medium text-slate-700">
                                    {{ $incentive->name }}
                                    <input type="number" wire:model="annualIncentiveAmounts.{{ $incentive->id }}" min="0" step="0.01" required class="mt-1.5 w-full rounded-xl border-slate-200">
                                    @error("annualIncentiveAmounts.{$incentive->id}")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                                </label>
                            @endforeach
                        </div>
                        @error('employeeId')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                        @error('academicYearId')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Simpan nilai tahunan</button>
                    </form>
                @else
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Pilih pegawai dan tahun ajaran untuk mengatur nominal.</p>
                @endif
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Tambahkan jenis insentif bertipe tetap tahunan terlebih dahulu.</p>
            @endif
        </article>
        @endif
    @elseif(in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true))
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">Pilih cabang untuk melihat dan mengatur jenis insentif serta tarif tunjangannya.</div>
    @endif
</section>

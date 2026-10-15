<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Jabatan & kompensasi</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola jabatan dan penugasan untuk tahun ajaran aktif. Satu jabatan non-kelas hanya memiliki satu pemegang, guru maksimal menjadi wali kelas untuk satu kelas, dan satu kelas hanya memiliki satu wali kelas.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="grid gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:grid-cols-2">
        @if(auth()->user()->role === 'admin_pusat')
            <label class="text-sm font-semibold text-slate-700">Cabang
                <select wire:model.live="branchId" class="mt-1.5 w-full rounded-xl border-slate-200">
                    <option value="">Pilih cabang</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <div class="text-sm font-semibold text-slate-700">Cabang
                <div class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 font-normal">{{ auth()->user()->branch?->name ?? 'Cabang pengguna' }}</div>
            </div>
        @endif
        <label class="text-sm font-semibold text-slate-700">Tahun ajaran berjalan
            <select wire:model.live="academicYearId" class="mt-1.5 w-full rounded-xl border-slate-200">
                <option value="">Tidak ada tahun ajaran aktif</option>
                @foreach($academicYears as $academicYear)
                    <option value="{{ $academicYear->id }}">{{ $academicYear->name }}{{ $academicYear->is_active ? ' · Aktif' : '' }}</option>
                @endforeach
            </select>
        </label>
    </section>

    @if($branchId && $academicYearId)
        @if(in_array(auth()->user()->role, ['admin_pusat', 'keuangan'], true))
            <section class="grid gap-6 xl:grid-cols-[1.25fr_1fr]">
                <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">Tabel kompensasi jabatan</h2>
                        <p class="mt-1 text-sm text-slate-500">Nilai berlaku bulanan pada tahun ajaran yang dipilih. Gaji jabatan adalah tambahan di atas gaji pokok pegawai.</p>
                    </div>
                    @if($positions->isNotEmpty())
                        <div class="divide-y divide-slate-100">
                            @foreach($positions as $position)
                                <form wire:key="position-{{ $position->id }}" wire:submit="savePosition('{{ $position->id }}')" class="space-y-3 p-4 {{ $position->is_active ? '' : 'bg-slate-50 opacity-75' }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $position->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $position->is_active ? 'Aktif' : 'Nonaktif' }} · {{ $position->assignments_count }} penugasan
                                        </span>
                                        <button type="button" wire:click="togglePosition('{{ $position->id }}')" wire:confirm="{{ $position->is_active ? 'Nonaktifkan jabatan ini? Payroll lama tidak berubah.' : 'Aktifkan kembali jabatan ini?' }}" class="text-xs font-semibold {{ $position->is_active ? 'text-rose-600' : 'text-emerald-700' }}">{{ $position->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </div>
                                    <label class="block text-xs font-semibold text-slate-500">Nama jabatan
                                        <input type="text" wire:model="positionInputs.{{ $position->id }}.name" maxlength="100" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        @error("positionInputs.{$position->id}.name")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                                    </label>
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                        <input type="checkbox" wire:model="positionInputs.{{ $position->id }}.requires_class" class="rounded border-slate-300 text-indigo-600">
                                        Penugasan ini untuk satu kelas (maksimal satu penugasan per kelas)
                                    </label>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <label class="text-xs font-semibold text-slate-500">Tambahan gaji/bulan (Rp)
                                            <input type="number" min="0" step="0.01" wire:model="positionInputs.{{ $position->id }}.monthly_salary_addition" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        </label>
                                        <label class="text-xs font-semibold text-slate-500">Tunjangan/bulan (Rp)
                                            <input type="number" min="0" step="0.01" wire:model="positionInputs.{{ $position->id }}.monthly_allowance" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        </label>
                                        <label class="text-xs font-semibold text-slate-500">Insentif/bulan (Rp)
                                            <input type="number" min="0" step="0.01" wire:model="positionInputs.{{ $position->id }}.monthly_incentive" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        </label>
                                    </div>
                                    <button type="submit" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Simpan kompensasi</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <p class="p-5 text-sm text-slate-500">Belum ada jabatan untuk tahun ajaran ini.</p>
                    @endif
                </article>

                <article class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div>
                        <h2 class="font-semibold text-slate-900">Tambah jabatan dinamis</h2>
                        <p class="mt-1 text-sm text-slate-500">Contoh: koordinator kurikulum, kepala laboratorium, atau jabatan lain sesuai kebutuhan cabang.</p>
                    </div>
                    <form wire:submit="addPosition" class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700">Nama jabatan
                            <input type="text" wire:model="newPositionName" maxlength="100" required class="mt-1 w-full rounded-xl border-slate-200">
                        </label>
                        @error('newPositionName')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="newPositionRequiresClass" class="rounded border-slate-300 text-indigo-600">
                            Penugasan ini untuk satu kelas (maksimal satu penugasan per kelas)
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Tambahan gaji per bulan (Rp)
                            <input type="number" min="0" step="0.01" wire:model="newMonthlySalaryAddition" required class="mt-1 w-full rounded-xl border-slate-200">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Tunjangan per bulan (Rp)
                            <input type="number" min="0" step="0.01" wire:model="newMonthlyAllowance" required class="mt-1 w-full rounded-xl border-slate-200">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Insentif per bulan (Rp)
                            <input type="number" min="0" step="0.01" wire:model="newMonthlyIncentive" required class="mt-1 w-full rounded-xl border-slate-200">
                        </label>
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Tambah jabatan</button>
                    </form>
                </article>
            </section>
        @else
            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Jabatan tersedia untuk penugasan</h2>
                <p class="mt-1 text-sm text-slate-500">Nilai kompensasi diatur oleh bagian keuangan. Admin cabang dapat menetapkan pegawai pada jabatan dan kelas.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($positions->where('is_active', true) as $position)
                        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">{{ $position->name }}@if($position->requires_class) · per kelas @endif</span>
                    @endforeach
                </div>
            </article>
        @endif

        @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang'], true))
            <section class="grid gap-6 xl:grid-cols-[0.9fr_1.4fr]">
                <article class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div>
                        <h2 class="font-semibold text-slate-900">Tetapkan jabatan pegawai</h2>
                        <p class="mt-1 text-sm text-slate-500">Setiap guru atau staff hanya dapat memegang satu jabatan non-kelas. Guru dapat menjadi wali kelas untuk satu kelas; guru bidang studi tetap dapat mengajar beberapa kelas melalui roster.</p>
                    </div>
                    <form wire:submit="assignPosition" class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700">Jabatan
                            <select wire:model.live="positionId" required class="mt-1 w-full rounded-xl border-slate-200">
                                <option value="">Pilih jabatan</option>
                                @foreach($positions->where('is_active', true) as $position)
                                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('positionId')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                        <label class="block text-sm font-medium text-slate-700">Guru / pegawai
                            <select wire:model="employeeId" required class="mt-1 w-full rounded-xl border-slate-200">
                                <option value="">Pilih pegawai</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->nip }} ({{ $employee->type }})</option>
                                @endforeach
                            </select>
                        </label>
                        @error('employeeId')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                        @if($positions->firstWhere('id', $positionId)?->requires_class)
                            <label class="block text-sm font-medium text-slate-700">Kelas
                                <select wire:model="className" required class="mt-1 w-full rounded-xl border-slate-200">
                                    <option value="">Pilih kelas</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class }}">{{ $class }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @error('className')<span class="block text-sm text-rose-600">{{ $message }}</span>@enderror
                            @if($classes->isEmpty())
                                <p class="text-xs text-amber-700">Buat kelas di menu Kelas & ruangan terlebih dahulu.</p>
                            @endif
                        @endif
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Simpan penugasan</button>
                    </form>
                </article>

                <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">Penugasan tahun ajaran {{ $academicYears->firstWhere('id', $academicYearId)?->name }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Jabatan</th><th class="px-4 py-3">Pegawai</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3">Kompensasi/bulan</th><th class="px-4 py-3"></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($assignments as $assignment)
                                    <tr wire:key="assignment-{{ $assignment->id }}">
                                        <td class="px-4 py-3 font-medium">{{ $assignment->position?->name }}</td>
                                        <td class="px-4 py-3">{{ $assignment->employee?->name }}<div class="text-xs text-slate-500">{{ $assignment->employee?->nip }}</div></td>
                                        <td class="px-4 py-3">{{ $assignment->class_name ?: '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-600">Gaji +{{ number_format((float) $assignment->position?->monthly_salary_addition, 0, ',', '.') }}<br>Tunj. {{ number_format((float) $assignment->position?->monthly_allowance, 0, ',', '.') }} · Ins. {{ number_format((float) $assignment->position?->monthly_incentive, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3"><button type="button" wire:click="removeAssignment('{{ $assignment->id }}')" wire:confirm="Hapus penugasan jabatan pegawai ini?" class="text-xs font-semibold text-rose-600 hover:underline">Hapus</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada penugasan pada tahun ajaran ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        @else
            <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Penugasan jabatan aktif</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse($assignments as $assignment)
                        <div class="flex flex-wrap justify-between gap-2 px-5 py-3 text-sm"><span class="font-medium">{{ $assignment->position?->name }} · {{ $assignment->employee?->name }}@if($assignment->class_name) · {{ $assignment->class_name }}@endif</span><span class="text-slate-500">Gaji + Rp {{ number_format((float) $assignment->position?->monthly_salary_addition, 0, ',', '.') }} · Tunjangan Rp {{ number_format((float) $assignment->position?->monthly_allowance, 0, ',', '.') }} · Insentif Rp {{ number_format((float) $assignment->position?->monthly_incentive, 0, ',', '.') }}</span></div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada pegawai yang ditetapkan.</p>
                    @endforelse
                </div>
            </article>
        @endif
    @elseif(! $academicYearId)
        <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-6 py-12 text-center text-sm text-amber-800">Belum ada tahun ajaran aktif. Aktifkan tahun ajaran yang sedang berjalan sebelum membuat penugasan.</div>
    @elseif(auth()->user()->role === 'admin_pusat')
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">Pilih cabang dan tahun ajaran untuk mengelola jabatan serta kompensasinya.</div>
    @endif
</section>

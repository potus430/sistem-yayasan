<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Generator Roster Pelajaran</h1>
        <p class="mt-1 text-sm text-slate-500">Penjadwalan greedy Senin–Jumat, jam 1–8, dengan pemeriksaan bentrok guru, kelas, dan ruang.</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @error('lessons')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror

    <form wire:submit="generate" class="space-y-5">
        <div class="grid gap-4 rounded-xl bg-white p-5 shadow-sm md:grid-cols-2">
            @if(auth()->user()->role === 'admin_pusat')
                <label class="text-sm font-medium">Cabang sekolah
                    <select wire:model.live="branchId" required class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">Pilih cabang</option>
                        @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->level }})</option>@endforeach
                    </select>
                </label>
            @endif
            <label class="text-sm font-medium">Tahun ajaran
                <select wire:model.live="academicYearId" required class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih tahun ajaran</option>
                    @foreach($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach
                </select>
            </label>
        </div>

        @if($isRosterAdmin)
            @if($rosterCount === 0)
                <div class="space-y-3">
                    @foreach($lessons as $index => $lesson)
                        <article wire:key="lesson-{{ $index }}" class="rounded-xl bg-white p-5 shadow-sm">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="font-semibold">Mata pelajaran {{ $index + 1 }}</h2>
                                @if(count($lessons) > 1)
                                    <button type="button" wire:click="removeLesson({{ $index }})" class="text-sm font-medium text-rose-600">Hapus</button>
                                @endif
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <label class="text-sm font-medium">Pelajaran
                                    <select wire:model="lessons.{{ $index }}.subject" required class="mt-1 w-full rounded-lg border-slate-300">
                                        <option value="">Pilih mata pelajaran</option>
                                        @foreach($subjects as $subject)<option value="{{ $subject->name }}">{{ $subject->name }}</option>@endforeach
                                    </select>
                                    @error("lessons.$index.subject")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                                </label>
                                <label class="text-sm font-medium">Guru
                                    <select wire:model="lessons.{{ $index }}.teacher_id" required class="mt-1 w-full rounded-lg border-slate-300">
                                        <option value="">Pilih guru</option>
                                        @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                                    </select>
                                </label>
                                <label class="text-sm font-medium">Kelas
                                    <select wire:model="lessons.{{ $index }}.class_name" required class="mt-1 w-full rounded-lg border-slate-300">
                                        <option value="">Pilih kelas</option>
                                        @foreach($classes as $class)<option value="{{ $class->name }}">{{ $class->name }}</option>@endforeach
                                    </select>
                                    @error("lessons.$index.class_name")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                                </label>
                                <label class="text-sm font-medium">Ruangan
                                    <select wire:model="lessons.{{ $index }}.room" required class="mt-1 w-full rounded-lg border-slate-300">
                                        <option value="">Pilih ruangan</option>
                                        @foreach($rooms as $room)<option value="{{ $room->name }}">{{ $room->name }}</option>@endforeach
                                    </select>
                                    @error("lessons.$index.room")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                                </label>
                                <label class="text-sm font-medium">Jam/minggu
                                    <input type="number" min="1" max="40" wire:model="lessons.{{ $index }}.weekly_hours" required class="mt-1 w-full rounded-lg border-slate-300">
                                </label>
                            </div>
                            @if($classes->isEmpty() || $rooms->isEmpty() || $subjects->isEmpty())
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Lengkapi master kelas, ruangan, dan mata pelajaran untuk cabang ini melalui menu <a class="font-semibold underline" href="{{ route('school-structure') }}">Kelas & ruangan</a> sebelum membuat roster.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="addLesson" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 font-medium hover:bg-slate-50">Tambah mata pelajaran</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-5 py-2.5 font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Generate roster tahun ajaran ini</button>
                </div>
            @else
                <p class="rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-900">Roster tahun ajaran ini sudah dibuat. Jadwal yang ada dipertahankan; admin dapat menukar jam secara manual di bawah.</p>
            @endif
            @error('academicYearId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('branchId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @endif
    </form>

    @if($rosterCount > 0)
        @if($isRosterAdmin)
            <form wire:submit="swapTimes" class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div>
                    <h2 class="font-semibold text-amber-950">Tukar jam dua pelajaran</h2>
                    <p class="mt-1 text-sm text-amber-900">Pilih dua jadwal. Guru, mata pelajaran, kelas, dan ruang tetap; posisi hari dan jam keduanya ditukar. Sistem menolak pertukaran yang menimbulkan bentrok.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-sm font-medium">Jadwal pertama
                        <select wire:model="swapSlotA" required class="mt-1 w-full rounded-lg border-amber-300 bg-white">
                            <option value="">Pilih jadwal</option>
                            @foreach($swapSlots as $slot)
                                <option value="{{ $slot->id }}">{{ ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'][$slot->weekday - 1] }} J{{ $slot->period }} · {{ $slot->class_name }} · {{ $slot->subject }} · {{ $slot->employee?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium">Jadwal kedua
                        <select wire:model="swapSlotB" required class="mt-1 w-full rounded-lg border-amber-300 bg-white">
                            <option value="">Pilih jadwal</option>
                            @foreach($swapSlots as $slot)
                                <option value="{{ $slot->id }}">{{ ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'][$slot->weekday - 1] }} J{{ $slot->period }} · {{ $slot->class_name }} · {{ $slot->subject }} · {{ $slot->employee?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @error('swapSlotA')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                @error('swapSlotB')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-amber-700 px-4 py-2.5 font-semibold text-white hover:bg-amber-800 disabled:opacity-60">Tukar jam</button>
            </form>
        @endif

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-semibold">Roster · {{ $academicYears->firstWhere('id', $academicYearId)?->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($rosterCount) }} slot jadwal</p><div class="mt-2 flex gap-3"><a href="{{ route('reports.download', ['report' => 'roster', 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null, 'academic_year_id' => $academicYearId, 'format' => 'pdf']) }}" class="text-xs font-semibold text-rose-700 hover:underline">Ekspor PDF</a><a href="{{ route('reports.download', ['report' => 'roster', 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null, 'academic_year_id' => $academicYearId, 'format' => 'xlsx']) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Ekspor Excel</a></div></div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">Baris per halaman
                    <select wire:model.live="perPage" aria-label="Jumlah roster per halaman" class="rounded-lg border-slate-200 py-2 text-sm">
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Hari</th><th class="px-4 py-3">Jam</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3">Pelajaran</th><th class="px-4 py-3">Guru</th><th class="px-4 py-3">Ruang</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rosterSlots as $slot)
                            <tr>
                                <td class="px-4 py-3">{{ ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'][$slot->weekday - 1] }}</td>
                                <td class="px-4 py-3">{{ $slot->period }}</td><td class="px-4 py-3">{{ $slot->class_name }}</td>
                                <td class="px-4 py-3">{{ $slot->subject }}</td><td class="px-4 py-3">{{ $slot->employee?->name }}</td><td class="px-4 py-3">{{ $slot->room }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($rosterSlots->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">{{ $rosterSlots->links() }}</div>
            @endif
        </div>
    @endif
</section>

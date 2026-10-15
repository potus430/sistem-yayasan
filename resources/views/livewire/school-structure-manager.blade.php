<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif

    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-violet-950 via-indigo-900 to-sky-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-200">Pengaturan operasional sekolah</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Kelas & ruangan</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Atur master kelas dan ruang belajar untuk siswa serta roster cabang ini.</p>
            </div>
            @if(auth()->user()->role === 'admin_pusat')
                <label class="w-full max-w-sm text-xs font-semibold uppercase tracking-wider text-indigo-100">Cabang
                    <select wire:model.live="branchId" class="mt-1.5 w-full rounded-xl border-0 bg-white px-4 py-3 text-sm font-medium normal-case tracking-normal text-slate-800">
                        <option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }} · {{ $branch->level }}</option>@endforeach
                    </select>
                </label>
            @endif
        </div>
    </div>

    @if($branchId)
        <div class="grid gap-6 xl:grid-cols-2">
            <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5">
                    <div><h2 class="text-lg font-bold text-slate-900">Daftar kelas</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($classes->count()) }} kelas terdaftar</p></div>
                    <button type="button" wire:click="create('class')" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><span class="text-lg leading-none">+</span>Tambah kelas</button>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($classes as $class)
                        <div wire:key="school-class-{{ $class->id }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-indigo-50/40">
                            <div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-700">{{ strtoupper(substr($class->name, 0, 2)) }}</span><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $class->name }}</p><p class="text-xs text-slate-500">Tersedia untuk data siswa dan roster</p></div></div>
                            <div class="flex shrink-0 gap-1"><button type="button" wire:click="edit('class', '{{ $class->id }}')" class="rounded-lg px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</button><button type="button" wire:click="delete('class', '{{ $class->id }}')" wire:confirm="Hapus kelas {{ $class->name }}? Kelas yang masih digunakan tidak dapat dihapus." class="rounded-lg px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button></div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center"><p class="font-semibold text-slate-800">Belum ada data kelas</p><p class="mt-1 text-sm text-slate-500">Tambahkan kelas untuk dapat memasukkan siswa dan menyusun roster.</p></div>
                    @endforelse
                </div>
            </article>

            <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5">
                    <div><h2 class="text-lg font-bold text-slate-900">Mata pelajaran</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($subjects->count()) }} mata pelajaran · digunakan saat menyusun roster</p></div>
                    <button type="button" wire:click="create('subject')" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-3.5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><span class="text-lg leading-none">+</span>Tambah mapel</button>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($subjects as $subject)
                        <div wire:key="school-subject-{{ $subject->id }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-violet-200 hover:bg-violet-50/40">
                            <div class="flex min-w-0 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-xs font-bold text-violet-700">M</span><p class="truncate text-sm font-semibold text-slate-800">{{ $subject->name }}</p></div>
                            <div class="flex shrink-0 gap-1"><button type="button" wire:click="edit('subject', '{{ $subject->id }}')" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">Edit</button><button type="button" wire:click="delete('subject', '{{ $subject->id }}')" wire:confirm="Hapus mata pelajaran {{ $subject->name }}? Mata pelajaran pada roster tidak dapat dihapus." class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada mata pelajaran. Tambahkan mapel untuk mengaktifkan generator roster.</p>
                    @endforelse
                </div>
            </article>

            <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5">
                    <div><h2 class="text-lg font-bold text-slate-900">Daftar ruangan</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($rooms->count()) }} ruangan terdaftar</p></div>
                    <button type="button" wire:click="create('room')" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-3.5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700"><span class="text-lg leading-none">+</span>Tambah ruangan</button>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($rooms as $room)
                        <div wire:key="school-room-{{ $room->id }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-sky-50/40">
                            <div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01"/></svg></span><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $room->name }}</p><p class="text-xs text-slate-500">Lokasi belajar untuk jadwal pelajaran</p></div></div>
                            <div class="flex shrink-0 gap-1"><button type="button" wire:click="edit('room', '{{ $room->id }}')" class="rounded-lg px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-100">Edit</button><button type="button" wire:click="delete('room', '{{ $room->id }}')" wire:confirm="Hapus ruangan {{ $room->name }}? Ruangan yang masih digunakan tidak dapat dihapus." class="rounded-lg px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button></div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center"><p class="font-semibold text-slate-800">Belum ada data ruangan</p><p class="mt-1 text-sm text-slate-500">Tambahkan ruangan sebelum membuat roster pelajaran.</p></div>
                    @endforelse
                </div>
            </article>
        </div>
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">Pilih cabang terlebih dahulu untuk melihat atau mengatur kelas dan ruangan.</div>
    @endif

    @if($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="structure-form-title">
            <form wire:submit="save" class="w-full max-w-lg space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">{{ ['class' => 'Master kelas', 'room' => 'Master ruangan', 'subject' => 'Master mata pelajaran'][$kind] }}</p><h2 id="structure-form-title" class="mt-1 text-2xl font-bold text-slate-900">{{ $editingId ? 'Ubah nama' : 'Tambah' }} {{ ['class' => 'kelas', 'room' => 'ruangan', 'subject' => 'mata pelajaran'][$kind] }}</h2></div>
                    <button type="button" wire:click="$set('showForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                @if(auth()->user()->role === 'admin_pusat')
                    <label class="block text-sm font-semibold text-slate-700">Cabang
                        <select wire:model="branchId" required class="mt-1.5 w-full rounded-xl border-slate-200"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                        @error('branchId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                @endif
                <label class="block text-sm font-semibold text-slate-700">Nama {{ ['class' => 'kelas', 'room' => 'ruangan', 'subject' => 'mata pelajaran'][$kind] }}
                    <input wire:model="name" required maxlength="{{ $kind === 'subject' ? 100 : 50 }}" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="{{ ['class' => 'Contoh: 7A atau TK B', 'room' => 'Contoh: Ruang 101', 'subject' => 'Contoh: Matematika'][$kind] }}">
                    @error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
                @if($editingId)
                    <p class="rounded-xl bg-indigo-50 px-4 py-3 text-xs leading-5 text-indigo-800">Mengubah nama akan memperbarui referensi pada data siswa dan roster secara otomatis. Menghapus data yang sedang digunakan akan ditolak.</p>
                @endif
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </div>
    @endif
</section>

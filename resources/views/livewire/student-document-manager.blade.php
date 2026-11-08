<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif

    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 p-6 text-white shadow-xl sm:p-8">
        <a href="{{ route('students') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-200 hover:text-white">← Kembali ke data siswa</a>
        <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-indigo-200">Arsip siswa privat</p>
        <div class="mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Dokumen {{ $student->name }}</h1>
                <p class="mt-2 text-sm text-indigo-100">NISN {{ $student->nisn }} · {{ $student->current_class }}</p>
            </div>
            <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-semibold text-indigo-100">Hanya dapat diakses admin cabang ini</span>
        </div>
    </div>

    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-7">
        <div class="mb-5">
            <h2 class="text-lg font-bold text-slate-900">Unggah dokumen</h2>
            <p class="mt-1 text-sm text-slate-500">Format pas foto: JPG, PNG, WEBP (maks. 5 MB). KK dan ijazah: PDF, JPG, PNG (maks. 10 MB per berkas).</p>
        </div>
        <form wire:submit="saveDocuments" class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach(\App\Models\StudentDocument::TYPES as $type => $label)
                    <label class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 text-sm font-semibold text-slate-800 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                        <span class="flex items-center justify-between gap-2">{{ $label }} @if($type === 'PAS_PHOTO')<span class="text-xs font-normal text-slate-500">Foto</span>@elseif($type === 'FAMILY_CARD')<span class="text-xs font-normal text-slate-500">KK</span>@elseif($type === 'PREVIOUS_DIPLOMA')<span class="text-xs font-normal text-slate-500">Jika ada</span>@endif</span>
                        <input type="file" wire:model="uploads.{{ $type }}" accept="{{ $type === 'PAS_PHOTO' ? 'image/jpeg,image/png,image/webp' : ($type === 'OTHER' ? '.pdf,image/jpeg,image/png,image/webp' : '.pdf,image/jpeg,image/png') }}" class="mt-3 block w-full cursor-pointer text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-800 hover:file:bg-indigo-200">
                        <span wire:loading wire:target="uploads.{{ $type }}" class="mt-2 block text-xs text-indigo-700">Mengunggah berkas…</span>
                        @if(isset($uploads[$type]))<span class="mt-2 block truncate text-xs font-medium text-emerald-700">{{ $uploads[$type]->getClientOriginalName() }}</span>@endif
                        @error('uploads.'.$type)<span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
            @error('uploads')<p class="text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            <div class="flex justify-end border-t border-slate-100 pt-4">
                <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">Simpan dokumen terpilih</button>
            </div>
        </form>
    </article>

    <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Dokumen tersimpan</h2>
            <p class="mt-1 text-sm text-slate-500">Berkas tidak disimpan di folder publik dan hanya diunduh melalui akses terautentikasi.</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($documents as $document)
                <div wire:key="student-document-{{ $document->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                            @if(str_starts_with($document->mime_type, 'image/'))
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            @else
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ \App\Models\StudentDocument::TYPES[$document->type] ?? 'Dokumen lainnya' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $document->original_name }} · {{ number_format($document->size_bytes / 1024, 0) }} KB · {{ $document->created_at->format('d M Y H:i') }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <a href="{{ route('student-documents.download', $document) }}" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50">Unduh</a>
                        <button type="button" wire:click="deleteDocument('{{ $document->id }}')" wire:confirm="Hapus dokumen ini secara permanen?" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">Hapus</button>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-semibold text-slate-800">Belum ada dokumen</p>
                    <p class="mt-1 text-sm text-slate-500">Unggah pas foto, kartu keluarga, atau ijazah terakhir jika tersedia.</p>
                </div>
            @endforelse
        </div>
    </article>
</section>

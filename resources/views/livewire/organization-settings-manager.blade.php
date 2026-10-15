<section class="mx-auto max-w-4xl space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Pengaturan yayasan</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Identitas aplikasi</h1>
        <p class="mt-2 text-sm text-slate-500">Atur nama aplikasi serta nama dan alamat yayasan yang tampil pada halaman dan dokumen.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-7">
        <label class="block text-sm font-semibold text-slate-700">Nama aplikasi
            <input type="text" wire:model="applicationName" required maxlength="120" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Contoh: Sistem Informasi Administrasi">
            <span class="mt-1 block text-xs font-normal text-slate-500">Untuk pengguna cabang, nama cabang akan menjadi judul utama aplikasi. Nama ini digunakan untuk admin pusat dan sebagai identitas umum.</span>
            @error('applicationName')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
        </label>

        <label class="block text-sm font-semibold text-slate-700">Nama yayasan
            <input type="text" wire:model="foundationName" required maxlength="180" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Contoh: Yayasan Harapan Bangsa">
            @error('foundationName')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
        </label>

        <label class="block text-sm font-semibold text-slate-700">Alamat yayasan
            <textarea wire:model="foundationAddress" rows="3" maxlength="2000" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Alamat kantor pusat yayasan"></textarea>
            @error('foundationAddress')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
        </label>

        <div class="space-y-3 rounded-xl border border-slate-200 p-4">
            <div>
                <label for="foundation-logo" class="block text-sm font-semibold text-slate-700">Logo yayasan</label>
                <p class="mt-1 text-xs text-slate-500">Format PNG, JPG, atau WebP. Ukuran maksimal 2 MB.</p>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                @if($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo yayasan" class="h-20 w-20 rounded-xl border border-slate-200 bg-white object-contain p-2">
                @elseif($settings->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) }}" alt="Logo {{ $foundationName }}" class="h-20 w-20 rounded-xl border border-slate-200 bg-white object-contain p-2">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-400">Belum ada logo</div>
                @endif
                <div class="min-w-0 flex-1">
                    <input id="foundation-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="logo" class="mt-1 text-xs text-indigo-600">Mengunggah gambar sementara…</div>
                    @error('logo')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>
                @if($settings->logo_path)
                    <button type="button" wire:click="removeLogo" wire:confirm="Hapus logo yayasan?" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus logo</button>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-indigo-100 bg-indigo-50/70 p-4">
            @if($settings->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) }}" alt="" class="h-14 w-14 rounded-lg bg-white object-contain p-1">
            @endif
            <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Nama aplikasi di akun cabang</p>
            <p class="mt-1 text-lg font-bold text-slate-900">Nama cabang · {{ $foundationName }}</p>
            <p class="mt-1 text-sm text-slate-600">Contoh: SD Harapan Bangsa · {{ $foundationName }}. Nama dan alamat tiap sekolah diubah dari menu Data Cabang.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Simpan identitas</button>
        </div>
    </form>
</section>

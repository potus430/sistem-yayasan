<section class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Pengaturan akun</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Profil saya</h1>
        <p class="mt-2 text-sm text-slate-500">Ubah nama, alamat email login, atau password. Role dan cabang akun dikelola administrator.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-7">
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700">Nama
                <input type="text" wire:model="name" required maxlength="255" autocomplete="name" class="mt-1.5 w-full rounded-xl border-slate-200">
                @error('name')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-slate-700">Email login
                <input type="email" wire:model="email" required maxlength="255" autocomplete="email" class="mt-1.5 w-full rounded-xl border-slate-200">
                @error('email')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h2 class="font-semibold text-slate-800">Ganti password</h2>
            <p class="mt-1 text-xs text-slate-500">Kosongkan password baru jika tidak ingin menggantinya. Minimal 8 karakter.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">Password baru
                    <input type="password" wire:model="password" minlength="8" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border-slate-200">
                    @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-medium text-slate-700">Konfirmasi password baru
                    <input type="password" wire:model="password_confirmation" minlength="8" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border-slate-200">
                </label>
            </div>
        </div>

        <label class="block text-sm font-semibold text-slate-700">Password saat ini
            <input type="password" wire:model="currentPassword" required autocomplete="current-password" class="mt-1.5 w-full rounded-xl border-slate-200">
            <span class="mt-1 block text-xs font-normal text-slate-500">Konfirmasi identitas diperlukan untuk menyimpan perubahan profil.</span>
            @error('currentPassword')<span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>@enderror
        </label>

        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Simpan perubahan</button>
        </div>
    </form>
</section>

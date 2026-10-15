<main class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-12">
    <section class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
        <div class="mb-8">
            @if(\App\Models\OrganizationSetting::current()->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(\App\Models\OrganizationSetting::current()->logo_path) }}" alt="Logo {{ \App\Models\OrganizationSetting::current()->foundation_name }}" class="mb-4 h-16 w-16 rounded-xl bg-white object-contain p-1">
            @endif
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">{{ \App\Models\OrganizationSetting::current()->foundation_name }}</p>
            <h1 class="mt-3 text-2xl font-bold text-slate-900">Masuk ke sistem</h1>
            <p class="mt-2 text-sm text-slate-500">Gunakan akun administrasi atau akun sekolah Anda.</p>
        </div>

        <form wire:submit="login" class="space-y-5">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Kata sandi</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Ingat saya
            </label>
            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                <span wire:loading.remove>Masuk</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>
    </section>
</main>

@php($settings = \App\Models\OrganizationSetting::current())
<main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#f4f6fb] px-4 py-12">
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-32 -top-40 h-[28rem] w-[28rem] rounded-full bg-indigo-200/50 blur-3xl"></div>
        <div class="absolute -bottom-48 -right-32 h-[34rem] w-[34rem] rounded-full bg-violet-200/50 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#94a3b8_0.7px,transparent_0.7px)] [background-size:22px_22px] opacity-20"></div>
    </div>
    <section class="relative grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/80 bg-white shadow-2xl shadow-indigo-950/10 md:grid-cols-[1fr_0.9fr]">
        <div class="relative hidden min-h-[38rem] flex-col justify-between overflow-hidden bg-slate-950 p-10 text-white md:flex">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.42),transparent_55%),linear-gradient(145deg,#111827,#0b1220_72%)]"></div>
            @if($settings->login_panel_image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->login_panel_image_path) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-35">
                <div class="absolute inset-0 bg-gradient-to-br from-slate-950/85 via-indigo-950/70 to-slate-950/80"></div>
            @endif
            <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full border border-white/10"></div>
            <div class="absolute -bottom-20 -left-4 h-56 w-56 rounded-full border border-white/10"></div>
            <div class="relative flex items-center gap-3">
                @if($settings->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) }}" alt="Logo {{ $settings->foundation_name }}" class="h-12 w-12 rounded-2xl bg-white object-contain p-1.5">
                @else
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-600 text-lg font-black shadow-lg">Y</span>
                @endif
                <div><p class="font-bold tracking-tight">{{ $settings->application_name }}</p><p class="text-xs text-slate-400">{{ $settings->foundation_name }}</p></div>
            </div>
            <div class="relative max-w-md pb-8">
                <span class="inline-flex items-center gap-2 rounded-full border border-indigo-300/20 bg-indigo-400/10 px-3 py-1.5 text-xs font-semibold text-indigo-100"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>{{ $settings->login_panel_badge }}</span>
                <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight">{{ $settings->login_panel_headline }}</h1>
                <p class="mt-4 text-sm leading-7 text-slate-200">{{ $settings->login_panel_description }}</p>
            </div>
            <p class="relative text-xs text-slate-500">{{ $settings->foundation_name }} · {{ now()->format('Y') }}</p>
        </div>
        <div class="flex items-center p-7 sm:p-10 md:p-12">
            <div class="w-full">
                <div class="mb-9 md:hidden">
                    @if($settings->logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) }}" alt="Logo {{ $settings->foundation_name }}" class="mb-5 h-12 w-12 rounded-2xl bg-white object-contain p-1 shadow ring-1 ring-slate-100">
                    @endif
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">{{ $settings->foundation_name }}</p>
                </div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Selamat datang kembali</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Masuk ke sistem</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan akun administrasi atau akun sekolah Anda.</p>

                <form wire:submit="login" class="mt-8 space-y-5">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
                        <input id="email" type="email" wire:model="email" autocomplete="username" required placeholder="nama@sekolah.sch.id"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                        @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Kata sandi</label>
                        <input id="password" type="password" wire:model="password" autocomplete="current-password" required placeholder="Masukkan kata sandi"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                        @error('password') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2.5 text-sm text-slate-600">
                        <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Ingat saya
                    </label>
                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3.5 font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 hover:shadow-indigo-600/30 disabled:opacity-60">
                        <span wire:loading.remove>Masuk</span>
                        <span wire:loading>Memproses...</span>
                    </button>
                </form>
                <p class="mt-8 text-center text-xs text-slate-400">Akses sistem dilindungi dan hanya untuk pengguna berwenang.</p>
            </div>
        </div>
    </section>
</main>

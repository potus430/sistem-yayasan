<!DOCTYPE html>
<html lang="id">
<head>
    @php($organizationSettings = \App\Models\OrganizationSetting::current())
    @php($applicationBrand = auth()->user()->branch?->name ?: $organizationSettings->application_name)
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ $applicationBrand }} · {{ $organizationSettings->foundation_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#f5f6fb] text-slate-800 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="bg-slate-950 text-white lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
            <div class="flex items-center gap-3 px-5 py-5">
                @if($organizationSettings->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($organizationSettings->logo_path) }}" alt="Logo {{ $organizationSettings->foundation_name }}" class="h-11 w-11 rounded-2xl bg-white object-contain p-1 shadow-lg">
                @else
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-600 text-lg font-black shadow-lg shadow-indigo-950/40">Y</span>
                @endif
                <div class="min-w-0"><a href="{{ route('dashboard') }}" class="block truncate font-bold tracking-tight">{{ $applicationBrand }}</a><span class="block truncate text-xs text-slate-400">{{ $organizationSettings->foundation_name }}</span></div>
            </div>
            <div class="mx-4 border-t border-white/10"></div>
            <nav class="flex gap-1 overflow-x-auto px-3 py-4 text-sm lg:flex-1 lg:flex-col lg:overflow-y-auto">
                <p class="hidden px-3 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 lg:block">Menu utama</p>
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg>Dashboard
                </a>
                <a href="{{ route('profile') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('profile') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>Profil saya
                </a>
                @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang', 'keuangan']))
                    <a href="{{ route('reports') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('reports') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h17M8 16v-5m4 5V7m4 9v-7m4 7v-3"/></svg>Laporan cabang
                    </a>
                @endif
                @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang']))
                    <p class="hidden px-3 pb-2 pt-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 lg:block">Data sekolah</p>
                    <a href="{{ route('position-compensation') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('position-compensation') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2M8 8h8"/></svg>Jabatan & kompensasi
                    </a>
                    @if(auth()->user()->role === 'admin_pusat')
                        <a href="{{ route('branches') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('branches') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01"/></svg>Data cabang
                        </a>
                        <a href="{{ route('users') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('users') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4.5a4 4 0 0 1 0 7.75M19 14a5 5 0 0 1 2 4v3"/></svg>Akun & akses
                        </a>
                        <a href="{{ route('organization-settings') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('organization-settings') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="9"/></svg>Identitas aplikasi
                        </a>
                    @endif
                    <a href="{{ route('employees') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('employees') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4.5a4 4 0 0 1 0 7.75M19 14a5 5 0 0 1 2 4v3"/></svg>Guru & pegawai
                    </a>
                    <a href="{{ route('students') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('students') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5Z"/><path d="M6 12v5c3.3 2.7 8.7 2.7 12 0v-5"/></svg>Data siswa
                    </a>
                    <a href="{{ route('school-structure') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('school-structure') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01"/></svg>Kelas & ruangan
                    </a>
                @endif
                @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang']))
                    <a href="{{ route('attendance') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('attendance') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/></svg>Absensi
                    </a>
                @endif
                @if(in_array(auth()->user()->role, ['admin_pusat', 'admin_cabang', 'guru', 'staff']))
                    <a href="{{ route('roster') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('roster') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Roster pelajaran
                    </a>
                @endif
                @if(in_array(auth()->user()->role, ['admin_pusat', 'keuangan']))
                    <p class="hidden px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 lg:block">Keuangan</p>
                    @if(auth()->user()->role === 'keuangan')
                        <a href="{{ route('position-compensation') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('position-compensation') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2M8 8h8"/></svg>Jabatan & kompensasi
                        </a>
                    @endif
                    <a href="{{ route('payroll') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('payroll') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>Penggajian
                    </a>
                    <a href="{{ route('incentives') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('incentives') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Insentif & tunjangan
                    </a>
                    <a href="{{ route('spp') }}" class="flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('spp') ? 'bg-indigo-500/20 text-indigo-200 ring-1 ring-inset ring-indigo-400/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Pembayaran SPP
                    </a>
                @endif
            </nav>
            <div class="hidden border-t border-white/10 p-4 lg:block">
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-500/20 font-bold text-indigo-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs capitalize text-slate-400">{{ str_replace('_', ' ', auth()->user()->role) }}</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button aria-label="Keluar" title="Keluar" class="text-slate-400 hover:text-rose-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></button></form>
                </div>
            </div>
        </aside>
        <div class="min-w-0 flex-1 lg:ml-64">
            <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-[1500px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                    <div><p class="text-xs font-medium text-slate-400">{{ $applicationBrand }} · {{ $organizationSettings->foundation_name }}</p><p class="font-semibold text-slate-800">{{ $title ?? 'Dashboard' }}</p></div>
                    <div class="flex items-center gap-3">
                        <span class="hidden rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold capitalize text-indigo-700 sm:inline-flex">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                        <form method="POST" action="{{ route('logout') }}" class="lg:hidden">@csrf<button class="rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Keluar</button></form>
                    </div>
                </div>
            </header>
            <main class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @yield('content')
        {{ $slot ?? '' }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>

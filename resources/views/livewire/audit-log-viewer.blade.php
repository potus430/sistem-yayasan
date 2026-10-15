<section class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Audit perubahan data</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Log Aktivitas</h1>
        <p class="mt-2 text-sm text-slate-500">Riwayat perubahan mencatat pengguna, waktu, alamat IP, dan informasi browser/perangkat yang dilaporkan browser. Setiap peninjauan halaman ini juga dicatat.</p>
        <div class="mt-3 flex gap-3"><a href="{{ route('reports.download', array_merge(['report' => 'activity-logs', 'format' => 'pdf'], $this->dateFilterQueryParameters())) }}" class="text-sm font-semibold text-rose-700 hover:underline">Ekspor log PDF</a><a href="{{ route('reports.download', array_merge(['report' => 'activity-logs', 'format' => 'xlsx'], $this->dateFilterQueryParameters())) }}" class="text-sm font-semibold text-emerald-700 hover:underline">Ekspor log Excel</a></div>
    </div>

    <div class="grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px]">
        <label class="text-sm font-semibold text-slate-700">Cari pengguna, IP, jenis data, atau ID data
            <input type="search" wire:model.live.debounce.300ms="search" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Ketik untuk mencari…">
        </label>
        <label class="text-sm font-semibold text-slate-700">Jenis aktivitas
            <select wire:model.live="event" class="mt-1.5 w-full rounded-xl border-slate-200">
                <option value="">Semua aktivitas</option>
                <option value="created">Tambah</option>
                <option value="updated">Ubah</option>
                <option value="deleted">Hapus</option>
                <option value="viewed">Tinjau log</option>
            </select>
        </label>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">@include('livewire.partials.date-range-filter')</div>

    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Waktu & pengguna</th>
                        <th class="px-4 py-3 font-semibold">Aktivitas & data</th>
                        <th class="px-4 py-3 font-semibold">IP & browser/perangkat</th>
                        <th class="px-4 py-3 font-semibold">Rincian perubahan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="font-medium text-slate-800">{{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i:s') }} WIB</p>
                                <p class="mt-1 text-slate-700">{{ $log->actor_name ?: 'Tidak diketahui' }}</p>
                                <p class="text-xs text-slate-500">{{ $log->actor_email ?: ($log->actor_role ?: 'Akun sistem') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-700' => $log->event === 'created',
                                    'bg-amber-50 text-amber-700' => $log->event === 'updated',
                                    'bg-rose-50 text-rose-700' => $log->event === 'deleted',
                                    'bg-sky-50 text-sky-700' => $log->event === 'viewed',
                                ])>{{ ['created' => 'Tambah', 'updated' => 'Ubah', 'deleted' => 'Hapus', 'viewed' => 'Tinjau log'][$log->event] ?? $log->event }}</span>
                                <p class="mt-2 break-all font-medium text-slate-700">{{ class_basename($log->auditable_type) }}</p>
                                <p class="break-all text-xs text-slate-500">{{ $log->auditable_id }}</p>
                                @if($log->branch_id)
                                    <p class="mt-1 break-all text-xs text-slate-400">Cabang ID: {{ $log->branch_id }}</p>
                                @endif
                            </td>
                            <td class="max-w-xs px-4 py-4">
                                <p class="font-mono text-xs text-slate-700">{{ $log->ip_address ?: '—' }}</p>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $log->user_agent ?: 'Informasi browser tidak tersedia' }}</p>
                                @if($log->url)
                                    <p class="mt-1 break-all text-xs text-slate-400">{{ $log->http_method }} {{ $log->url }}</p>
                                @endif
                            </td>
                            <td class="min-w-72 px-4 py-4">
                                @if($log->old_values)
                                    <details>
                                        <summary class="cursor-pointer font-semibold text-indigo-700">Lihat perubahan</summary>
                                        <div class="mt-2 space-y-2">
                                            @if($log->old_values)
                                                <div><p class="text-xs font-semibold text-slate-500">Sebelum</p><pre class="mt-1 max-h-48 overflow-auto rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                            @endif
                                            @if($log->new_values)
                                                <div><p class="text-xs font-semibold text-slate-500">Sesudah</p><pre class="mt-1 max-h-48 overflow-auto rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                            @endif
                                        </div>
                                    </details>
                                @elseif($log->new_values)
                                    <details>
                                        <summary class="cursor-pointer font-semibold text-indigo-700">Lihat data baru</summary>
                                        <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">Data sebelum/sesudah tidak tersedia.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">Belum ada aktivitas yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $logs->links() }}</div>
    </div>

    <p class="text-xs text-slate-400">Catatan: browser tidak memberikan nama host PC yang dapat diverifikasi. Informasi perangkat yang dicatat berasal dari user-agent browser, dan IP bergantung pada konfigurasi jaringan/proxy.</p>
</section>

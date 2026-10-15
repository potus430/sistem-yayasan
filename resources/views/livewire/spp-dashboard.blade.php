<section class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Pemantauan SPP</h1>
        <p class="mt-1 text-sm text-slate-500">Pantau status tagihan per siswa dan cabang sekolah.</p>
    </div>

    <div class="grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-3">
        @if(auth()->user()->role === 'admin_pusat')
            <label class="text-sm font-medium">Cabang
                <select wire:model.live="branchId" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                </select>
            </label>
        @endif
        <label class="text-sm font-medium">Tahun ajaran
            <select wire:model.live="academicYearId" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">Semua tahun ajaran</option>
                @foreach($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-medium">Bulan
            <select wire:model.live="month" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">Semua bulan</option>
                @foreach(range(1, 12) as $number)<option value="{{ $number }}">{{ \Illuminate\Support\Carbon::create()->month($number)->translatedFormat('F') }}</option>@endforeach
            </select>
        </label>
    </div>

    <form wire:submit="generateMonthlyBills" class="grid gap-4 rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50/60 p-5 shadow-sm md:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2"><h2 class="font-semibold text-slate-900">Buat tagihan bulanan</h2><p class="mt-1 text-xs leading-5 text-slate-500">Tagihan yang sudah ada tidak akan ditimpa; aman dijalankan kembali.</p></div>
        <label class="text-sm font-medium text-slate-700">Bulan
            <select wire:model="generationMonth" required class="mt-1 w-full rounded-lg border-slate-200">
                @foreach(range(1, 12) as $number)<option value="{{ $number }}">{{ \Illuminate\Support\Carbon::create()->month($number)->translatedFormat('F') }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-medium text-slate-700">Nominal tagihan (Rp)
            <input wire:model="generationAmount" type="number" min="1" step="0.01" required placeholder="Contoh: 350000" class="mt-1 w-full rounded-lg border-slate-200">
            @error('generationAmount')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
        </label>
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Buat untuk siswa</button>
        @error('generationMonth')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('branchId')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('academicYearId')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Total tagihan</p><p class="mt-2 text-2xl font-bold">{{ number_format((int) ($summary->total_bills ?? 0)) }}</p></article>
        <article class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Lunas</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) ($summary->paid_bills ?? 0)) }}</p></article>
        <article class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Belum lunas</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((int) ($summary->unpaid_bills ?? 0)) }}</p></article>
        <article class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Dana terkumpul</p><p class="mt-2 text-xl font-bold">Rp {{ number_format((float) ($summary->collected ?? 0), 0, ',', '.') }}</p></article>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-semibold">Rincian tagihan</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($billings->total()) }} data sesuai filter</p><a href="{{ route('reports.download', ['report' => 'spp', 'branch_id' => auth()->user()->role === 'admin_pusat' ? $branchId : null]) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 hover:underline">Ekspor laporan CSV</a></div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-600">Baris per halaman
                <select wire:model.live="perPage" aria-label="Jumlah tagihan per halaman" class="rounded-lg border-slate-200 py-2 text-sm">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Siswa</th><th class="px-4 py-3">Cabang</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3">Bulan</th><th class="px-4 py-3">Jumlah</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($billings as $billing)
                        <tr>
                            <td class="px-4 py-3">{{ $billing->student?->name ?? 'Siswa terhapus' }}<span class="block text-xs text-slate-500">{{ $billing->student?->nisn }}</span></td>
                            <td class="px-4 py-3">{{ $billing->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $billing->student?->current_class ?? '-' }}</td>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::create()->month($billing->month)->translatedFormat('F') }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-800">Rp {{ number_format((float) $billing->amount, 0, ',', '.') }}</span>
                                @if((float) $billing->paid_amount > 0 && $billing->status !== 'PAID')<span class="mt-1 block text-xs text-sky-700">Terbayar Rp {{ number_format((float) $billing->paid_amount, 0, ',', '.') }}</span>@endif
                                @if($billing->payments->isNotEmpty())
                                    <details class="mt-1 text-xs text-slate-500"><summary class="cursor-pointer">{{ $billing->payments->count() }} riwayat pembayaran</summary><ul class="mt-1 space-y-1">
                                        @foreach($billing->payments as $payment)<li>{{ $payment->paid_at?->format('d/m/Y H:i') }} · {{ $payment->payment_method }} · Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}{{ $payment->reference ? ' · '.$payment->reference : '' }} · {{ $payment->receiver?->name ?? 'Admin' }}</li>@endforeach
                                    </ul></details>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $billing->status === 'PAID' ? 'bg-emerald-100 text-emerald-700' : ((float) $billing->paid_amount > 0 ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700') }}">{{ $billing->status === 'PAID' ? 'Lunas' : ((float) $billing->paid_amount > 0 ? 'Cicilan' : 'Belum lunas') }}</span>
                                @if($billing->status !== 'PAID')<button type="button" wire:click="openPayment('{{ $billing->id }}')" class="ml-2 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Catat bayar</button>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada tagihan pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($billings->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $billings->links() }}</div>
        @endif
    </div>

    @if($showPaymentForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="spp-payment-title">
            <form wire:submit="recordPayment" class="my-auto w-full max-w-lg space-y-5 rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Kas sekolah</p><h2 id="spp-payment-title" class="mt-1 text-2xl font-bold text-slate-900">Catat pembayaran SPP</h2><p class="mt-1 text-sm text-slate-500">Pembayaran parsial didukung; riwayat tidak dapat diubah dari halaman ini.</p></div>
                    <button type="button" wire:click="$set('showPaymentForm', false)" aria-label="Tutup" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <label class="block text-sm font-semibold text-slate-700">Nominal diterima (Rp)
                    <input wire:model="payAmount" type="number" min="0.01" step="0.01" required class="mt-1.5 w-full rounded-xl border-slate-200">
                    @error('payAmount')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">Metode
                        <select wire:model="payMethod" class="mt-1.5 w-full rounded-xl border-slate-200"><option value="TUNAI">Tunai</option><option value="TRANSFER">Transfer</option><option value="QRIS">QRIS</option><option value="LAINNYA">Lainnya</option></select>
                        @error('payMethod')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Nomor referensi
                        <input wire:model="payReference" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Opsional">
                        @error('payReference')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="block text-sm font-semibold text-slate-700">Catatan
                    <textarea wire:model="payNotes" rows="2" maxlength="1000" class="mt-1.5 w-full rounded-xl border-slate-200" placeholder="Catatan pembayaran (opsional)"></textarea>
                    @error('payNotes')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="$set('showPaymentForm', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan pembayaran</button>
                </div>
            </form>
        </div>
    @endif
</section>

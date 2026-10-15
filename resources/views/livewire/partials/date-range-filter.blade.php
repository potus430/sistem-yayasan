<div class="grid gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5">
    <label class="text-sm font-medium text-slate-700">Rentang waktu
        <select wire:model.live="dateFilterMode" class="mt-1 w-full rounded-lg border-slate-300 bg-white">
            <option value="all">Semua waktu</option>
            <option value="date">Tanggal</option>
            <option value="month">Bulan</option>
            <option value="year">Tahun</option>
            <option value="range">Dari tanggal - sampai tanggal</option>
        </select>
    </label>
    @if($dateFilterMode === 'date')
        <label class="text-sm font-medium text-slate-700">Tanggal
            <input type="date" wire:model.live="dateFilterDate" class="mt-1 w-full rounded-lg border-slate-300 bg-white">
        </label>
        @error('dateFilterDate')<span class="self-end text-sm text-rose-600">{{ $message }}</span>@enderror
    @elseif($dateFilterMode === 'month')
        <label class="text-sm font-medium text-slate-700">Bulan
            <input type="month" wire:model.live="dateFilterMonth" class="mt-1 w-full rounded-lg border-slate-300 bg-white">
        </label>
        @error('dateFilterMonth')<span class="self-end text-sm text-rose-600">{{ $message }}</span>@enderror
    @elseif($dateFilterMode === 'year')
        <label class="text-sm font-medium text-slate-700">Tahun
            <input type="number" min="1900" max="2100" step="1" wire:model.live="dateFilterYear" class="mt-1 w-full rounded-lg border-slate-300 bg-white" placeholder="YYYY">
        </label>
        @error('dateFilterYear')<span class="self-end text-sm text-rose-600">{{ $message }}</span>@enderror
    @elseif($dateFilterMode === 'range')
        <label class="text-sm font-medium text-slate-700">Dari tanggal
            <input type="date" wire:model.live="dateFilterFrom" class="mt-1 w-full rounded-lg border-slate-300 bg-white">
            @error('dateFilterFrom')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Sampai tanggal
            <input type="date" wire:model.live="dateFilterTo" class="mt-1 w-full rounded-lg border-slate-300 bg-white">
            @error('dateFilterTo')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
        </label>
    @endif
</div>

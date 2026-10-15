<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

trait HasDateRangeFilter
{
    public string $dateFilterMode = 'all';

    public string $dateFilterDate = '';

    public string $dateFilterMonth = '';

    public string $dateFilterYear = '';

    public string $dateFilterFrom = '';

    public string $dateFilterTo = '';

    public function updatedDateFilterMode(): void
    {
        $this->resetValidation(['dateFilterMode', 'dateFilterDate', 'dateFilterMonth', 'dateFilterYear', 'dateFilterFrom', 'dateFilterTo']);
        $this->resetPage();
    }

    public function updatedDateFilterDate(): void
    {
        $this->resetValidation(['dateFilterDate']);
        $this->resetPage();
    }

    public function updatedDateFilterMonth(): void
    {
        $this->resetValidation(['dateFilterMonth']);
        $this->resetPage();
    }

    public function updatedDateFilterYear(): void
    {
        $this->resetValidation(['dateFilterYear']);
        $this->resetPage();
    }

    public function updatedDateFilterFrom(): void
    {
        $this->resetValidation(['dateFilterFrom', 'dateFilterTo']);
        $this->resetPage();
    }

    public function updatedDateFilterTo(): void
    {
        $this->resetValidation(['dateFilterFrom', 'dateFilterTo']);
        $this->resetPage();
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function dateFilterBounds(): array
    {
        return match ($this->dateFilterMode) {
            'date' => $this->dateFilterDate !== ''
                ? [$this->validDate($this->dateFilterDate, 'dateFilterDate'), $this->validDate($this->dateFilterDate, 'dateFilterDate')]
                : [null, null],
            'month' => $this->monthBounds(),
            'year' => $this->yearBounds(),
            'range' => $this->rangeBounds(),
            'all' => [null, null],
            default => throw ValidationException::withMessages(['dateFilterMode' => 'Pilih jenis filter waktu yang valid.']),
        };
    }

    protected function applyDateFilter(Builder $query, string $column): Builder
    {
        [$from, $to] = $this->dateFilterBounds();

        return $query
            ->when($from, fn (Builder $query, string $date) => $query->whereDate($column, '>=', $date))
            ->when($to, fn (Builder $query, string $date) => $query->whereDate($column, '<=', $date));
    }

    /**
     * @return array{from?: string, to?: string}
     */
    public function dateFilterQueryParameters(): array
    {
        [$from, $to] = $this->dateFilterBounds();

        return array_filter(['from' => $from, 'to' => $to]);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function monthBounds(): array
    {
        if ($this->dateFilterMonth === '') {
            return [null, null];
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->dateFilterMonth)) {
            throw ValidationException::withMessages(['dateFilterMonth' => 'Pilih bulan dan tahun yang valid.']);
        }

        $month = Carbon::createFromFormat('!Y-m', $this->dateFilterMonth);

        return [$month->toDateString(), $month->endOfMonth()->toDateString()];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function yearBounds(): array
    {
        if ($this->dateFilterYear === '') {
            return [null, null];
        }

        if (! preg_match('/^\d{4}$/', $this->dateFilterYear) || (int) $this->dateFilterYear < 1900 || (int) $this->dateFilterYear > 2100) {
            throw ValidationException::withMessages(['dateFilterYear' => 'Masukkan tahun antara 1900 dan 2100.']);
        }

        return ["{$this->dateFilterYear}-01-01", "{$this->dateFilterYear}-12-31"];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function rangeBounds(): array
    {
        $from = $this->dateFilterFrom !== '' ? $this->validDate($this->dateFilterFrom, 'dateFilterFrom') : null;
        $to = $this->dateFilterTo !== '' ? $this->validDate($this->dateFilterTo, 'dateFilterTo') : null;

        if ($from && $to && $to < $from) {
            throw ValidationException::withMessages(['dateFilterTo' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }

        return [$from, $to];
    }

    private function validDate(string $value, string $field): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw ValidationException::withMessages([$field => 'Masukkan tanggal yang valid.']);
        }

        return $value;
    }
}

<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\SppBilling;
use App\Models\SppPayment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Ramsey\Uuid\Uuid;

class SppDashboard extends Component
{
    use WithPagination;

    public string $branchId = '';

    public string $academicYearId = '';

    public string $month = '';

    public int $perPage = 25;

    public string $generationMonth = '';

    public string $generationAmount = '';

    public string $payBillingId = '';

    public string $payAmount = '';

    public string $payReference = '';

    public string $payMethod = 'TUNAI';

    public string $payNotes = '';

    public bool $showPaymentForm = false;

    public function mount(): void
    {
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = auth()->user()->branch_id;
        }

        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->generationMonth = (string) now()->month;
    }

    public function updated($property): void
    {
        if (in_array($property, ['branchId', 'academicYearId', 'month'], true)) {
            $this->resetPage();
        }
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function generateMonthlyBills(): void
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum membuat tagihan.');
        $validated = $this->validate([
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'generationMonth' => ['required', 'integer', 'between:1,12'],
            'generationAmount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ]);

        $students = Student::query()->where('branch_id', $branchId)->orderBy('id')->get(['id', 'branch_id']);
        $createdCount = 0;
        foreach ($students->chunk(500) as $chunk) {
            $rows = $chunk->map(fn (Student $student): array => [
                'id' => Uuid::uuid7()->toString(),
                'student_id' => $student->id,
                'branch_id' => $branchId,
                'academic_year_id' => $validated['academicYearId'],
                'month' => $validated['generationMonth'],
                'amount' => $validated['generationAmount'],
                'paid_amount' => 0,
                'status' => 'UNPAID',
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            $createdCount += DB::table('spp_billings')->insertOrIgnore($rows);
        }

        session()->flash('success', "{$createdCount} tagihan berhasil dibuat. Tagihan yang sudah ada tidak ditimpa.");
        $this->resetPage();
    }

    public function openPayment(string $billingId): void
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum mencatat pembayaran.');
        $billing = SppBilling::query()
            ->where('branch_id', $branchId)
            ->whereKey($billingId)
            ->firstOrFail();
        abort_if($billing->status === 'PAID', 422, 'Tagihan ini sudah lunas.');

        $this->payBillingId = $billing->id;
        $this->payAmount = number_format(max(0, (float) $billing->amount - (float) $billing->paid_amount), 2, '.', '');
        $this->payReference = '';
        $this->payMethod = 'TUNAI';
        $this->payNotes = '';
        $this->resetValidation();
        $this->showPaymentForm = true;
    }

    public function recordPayment(): void
    {
        $this->resetValidation();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        abort_unless($branchId, 422, 'Pilih cabang sebelum mencatat pembayaran.');
        $validated = $this->validate([
            'payBillingId' => ['required', 'uuid'],
            'payAmount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'payReference' => ['nullable', 'string', 'max:100'],
            'payMethod' => ['required', Rule::in(['TUNAI', 'TRANSFER', 'QRIS', 'LAINNYA'])],
            'payNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($branchId, $validated, $user): void {
            $billing = SppBilling::query()
                ->where('branch_id', $branchId)
                ->whereKey($validated['payBillingId'])
                ->lockForUpdate()
                ->firstOrFail();
            $remaining = round((float) $billing->amount - (float) $billing->paid_amount, 2);
            $amount = round((float) $validated['payAmount'], 2);

            if ($billing->status === 'PAID' || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'payAmount' => 'Nominal pembayaran melebihi sisa tagihan atau tagihan sudah lunas.',
                ]);
            }

            SppPayment::query()->create([
                'spp_billing_id' => $billing->id,
                'branch_id' => $branchId,
                'received_by' => $user->id,
                'amount' => $amount,
                'reference' => $validated['payReference'] ?: null,
                'payment_method' => $validated['payMethod'],
                'notes' => $validated['payNotes'] ?: null,
                'paid_at' => now(),
            ]);

            $paidAmount = round((float) $billing->paid_amount + $amount, 2);
            $billing->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= (float) $billing->amount ? 'PAID' : 'UNPAID',
                'paid_at' => $paidAmount >= (float) $billing->amount ? now() : null,
            ]);
        });

        $this->showPaymentForm = false;
        session()->flash('success', 'Pembayaran berhasil dicatat dan riwayatnya tersimpan.');
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $branches = $user->role === 'admin_pusat' ? Branch::query()->orderBy('name')->get() : collect();
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get();

        $billingQuery = SppBilling::query()
            ->with(['student:id,name,nisn,current_class', 'branch:id,name', 'payments.receiver:id,name'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($this->academicYearId, fn ($query) => $query->where('academic_year_id', $this->academicYearId))
            ->when($this->month !== '', fn ($query) => $query->where('month', $this->month));

        $summary = (clone $billingQuery)
            ->selectRaw("COUNT(*) as total_bills, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_bills, SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) as unpaid_bills, SUM(paid_amount) as collected")
            ->first();
        $billings = $billingQuery->orderBy('branch_id')->orderBy('month')->orderBy('student_id')->paginate($this->perPage);

        return view('livewire.spp-dashboard', compact('branches', 'academicYears', 'summary', 'billings'))
            ->layout('layouts.app', ['title' => 'Pemantauan SPP']);
    }
}

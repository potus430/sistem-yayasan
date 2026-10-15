<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\SppBilling;
use App\Models\SppClassFee;
use App\Models\SppPayment;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Ramsey\Uuid\Uuid;

class SppDashboard extends Component
{
    use HasDateRangeFilter, WithPagination;

    public string $branchId = '';

    public string $academicYearId = '';

    public string $month = '';

    public int $perPage = 25;

    public string $generationMonth = '';

    public string $generationAmount = '';

    /** @var array<string, string> */
    public array $classFeeAmounts = [];

    public string $payBillingId = '';

    public string $payAmount = '';

    public string $payReference = '';

    public string $payMethod = 'TUNAI';

    public string $payNotes = '';

    public bool $showPaymentForm = false;

    public function mount(): void
    {
        if (! in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true)) {
            $this->branchId = auth()->user()->branch_id;
        }

        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->generationMonth = (string) now()->month;
        $this->loadClassFeeAmounts();
    }

    public function updated($property): void
    {
        if (in_array($property, ['branchId', 'academicYearId', 'month'], true)) {
            $this->resetPage();
        }

        if (in_array($property, ['branchId', 'academicYearId'], true)) {
            $this->loadClassFeeAmounts();
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
        abort_unless(auth()->user()->role === 'keuangan_pusat', 403);
        $user = auth()->user();
        $branchId = $this->effectiveBranchId($user);
        abort_unless($branchId, 422, 'Pilih cabang sebelum membuat tagihan.');
        $validated = $this->validate([
            'branchId' => ['required', 'uuid', Rule::exists('branches', 'id')],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'generationMonth' => ['required', 'integer', 'between:1,12'],
        ]);

        $students = Student::query()->where('branch_id', $branchId)->orderBy('id')->get(['id', 'branch_id', 'current_class']);
        $classIdsByName = DB::table('school_classes')
            ->where('branch_id', $branchId)
            ->whereIn('name', $students->pluck('current_class')->unique())
            ->pluck('id', 'name');
        $feesByClassId = SppClassFee::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $validated['academicYearId'])
            ->whereIn('school_class_id', $classIdsByName->values())
            ->pluck('amount', 'school_class_id');
        $feeAmounts = $classIdsByName->mapWithKeys(
            fn (string $classId, string $className): array => [$className => $feesByClassId[$classId] ?? null]
        );
        $missingClasses = $students->pluck('current_class')->unique()->reject(
            fn (string $className): bool => $feeAmounts->has($className) && $feeAmounts[$className] !== null
        );
        if ($missingClasses->isNotEmpty()) {
            $this->addError('generationMonth', 'Tarif SPP belum diatur untuk kelas: '.$missingClasses->implode(', ').'. Atur tarif per kelas terlebih dahulu.');

            return;
        }
        $createdCount = 0;
        DB::transaction(function () use ($students, $branchId, $validated, $feeAmounts, &$createdCount): void {
            foreach ($students->chunk(500) as $chunk) {
                $rows = $chunk->map(fn (Student $student): array => [
                    'id' => Uuid::uuid7()->toString(),
                    'student_id' => $student->id,
                    'branch_id' => $branchId,
                    'academic_year_id' => $validated['academicYearId'],
                    'month' => $validated['generationMonth'],
                    'amount' => $feeAmounts[$student->current_class],
                    'paid_amount' => 0,
                    'status' => 'UNPAID',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                $createdCount += DB::table('spp_billings')->insertOrIgnore($rows);
                $createdIds = DB::table('spp_billings')
                    ->whereIn('id', array_column($rows, 'id'))
                    ->pluck('id')
                    ->all();
                $createdRows = array_filter($rows, fn (array $row): bool => in_array($row['id'], $createdIds, true));
                $auditEntries = array_map(fn (array $row): array => [
                    'event' => 'created',
                    'auditable_type' => SppBilling::class,
                    'auditable_id' => $row['id'],
                    'branch_id' => $branchId,
                    'old_values' => [],
                    'new_values' => array_diff_key($row, array_flip(['created_at', 'updated_at'])),
                ], $createdRows);
                app(AuditLogger::class)->recordMany(array_values($auditEntries));
            }
        });

        session()->flash('success', "{$createdCount} tagihan berhasil dibuat. Tagihan yang sudah ada tidak ditimpa.");
        $this->resetPage();
    }

    public function openPayment(string $billingId): void
    {
        abort_unless(auth()->user()->role === 'keuangan', 403);
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
        abort_unless(auth()->user()->role === 'keuangan', 403);
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

    public function saveClassFee(string $classId): void
    {
        abort_unless(in_array(auth()->user()->role, ['admin_pusat', 'keuangan_pusat'], true), 403);
        $branchId = $this->effectiveBranchId(auth()->user());
        abort_unless($branchId && $this->academicYearId, 422, 'Pilih cabang dan tahun ajaran terlebih dahulu.');

        $validated = $this->validate([
            "classFeeAmounts.{$classId}" => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        $class = DB::table('school_classes')
            ->where('branch_id', $branchId)
            ->where('id', $classId)
            ->first();
        abort_unless($class, 404);

        SppClassFee::query()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'academic_year_id' => $this->academicYearId,
                'school_class_id' => $class->id,
            ],
            ['amount' => $validated['classFeeAmounts'][$classId]]
        );
        session()->flash('success', "Tarif SPP kelas {$class->name} berhasil disimpan.");
    }

    private function effectiveBranchId($user): ?string
    {
        return in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true)
            ? ($this->branchId ?: null)
            : ($user->branch_id ?: null);
    }

    private function loadClassFeeAmounts(): void
    {
        $this->classFeeAmounts = [];
        $branchId = $this->effectiveBranchId(auth()->user());
        if (! $branchId || ! $this->academicYearId) {
            return;
        }

        $classes = DB::table('school_classes')->where('branch_id', $branchId)->pluck('id', 'name');
        $fees = SppClassFee::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $this->academicYearId)
            ->pluck('amount', 'school_class_id');
        foreach ($classes as $name => $id) {
            $this->classFeeAmounts[$id] = (string) ($fees[$id] ?? '0');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $this->effectiveBranchId($user);
        $branches = in_array($user->role, ['admin_pusat', 'keuangan_pusat'], true)
            ? Branch::query()->whereIn('level', ['TK', 'SD', 'SMP', 'SMA'])->orderBy('name')->get()
            : collect();
        $classes = $branchId
            ? DB::table('school_classes')->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name'])
            : collect();
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get();

        $billingQuery = SppBilling::query()
            ->with(['student:id,name,nisn,current_class', 'branch:id,name', 'payments.receiver:id,name'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($this->academicYearId, fn ($query) => $query->where('academic_year_id', $this->academicYearId))
            ->when($this->month !== '', fn ($query) => $query->where('month', $this->month));
        $this->applyDateFilter($billingQuery, 'spp_billings.created_at');

        $summary = (clone $billingQuery)
            ->selectRaw("COUNT(*) as total_bills, SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_bills, SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) as unpaid_bills, SUM(paid_amount) as collected")
            ->first();
        $billings = $billingQuery->orderBy('branch_id')->orderBy('month')->orderBy('student_id')->paginate($this->perPage);

        return view('livewire.spp-dashboard', compact('branches', 'academicYears', 'summary', 'billings', 'classes'))
            ->layout('layouts.app', ['title' => 'Pemantauan SPP']);
    }
}

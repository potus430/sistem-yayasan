<?php

namespace Tests\Feature;

use App\Livewire\PayrollManager;
use App\Livewire\SppDashboard;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CentralFinanceAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_spp_fees_are_set_per_class_and_only_branch_finance_can_record_payment(): void
    {
        $branch = Branch::query()->create([
            'name' => 'SPP Access Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $class = SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => 'Class Fee 1']);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => Str::random(15),
            'name' => 'SPP Access Student',
            'current_class' => $class->name,
        ]);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $centralAdmin = $this->createUser('admin_pusat');
        $centralFinance = $this->createUser('keuangan_pusat');
        $branchFinance = $this->createUser('keuangan', $branch);
        $branchAdmin = $this->createUser('admin_cabang', $branch);

        $this->actingAs($centralAdmin)->get(route('spp'))->assertOk();
        $this->actingAs($centralFinance)->get(route('spp'))->assertOk();
        $this->actingAs($branchFinance)->get(route('spp'))->assertOk();
        $this->actingAs($branchAdmin)->get(route('spp'))->assertForbidden();

        Livewire::actingAs($centralAdmin)
            ->test(SppDashboard::class)
            ->set('branchId', $branch->id)
            ->set("classFeeAmounts.{$class->id}", '475000')
            ->call('saveClassFee', $class->id)
            ->assertHasNoErrors();

        Livewire::actingAs($branchFinance)
            ->test(SppDashboard::class)
            ->call('saveClassFee', $class->id)
            ->assertForbidden();

        Livewire::actingAs($centralFinance)
            ->test(SppDashboard::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $year->id)
            ->set('generationMonth', '10')
            ->call('generateMonthlyBills')
            ->assertHasNoErrors();

        $this->actingAs($branchFinance)
            ->get(route('spp'))
            ->assertOk()
            ->assertSee('Pembayaran SPP')
            ->assertSee('Terima pembayaran')
            ->assertDontSee('Kelas & ruangan');
        $this->actingAs($branchFinance)->get(route('school-structure'))->assertForbidden();

        $billing = \App\Models\SppBilling::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('475000.00', $billing->amount);
        Livewire::actingAs($centralFinance)
            ->test(SppDashboard::class)
            ->set('branchId', $branch->id)
            ->call('openPayment', $billing->id)
            ->assertForbidden();

        Livewire::actingAs($branchFinance)
            ->test(SppDashboard::class)
            ->call('openPayment', $billing->id)
            ->set('payAmount', '475000')
            ->call('recordPayment')
            ->assertHasNoErrors();
        $this->assertSame('PAID', $billing->fresh()->status);
    }

    public function test_branch_finance_can_only_view_and_print_payroll_details(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Payroll Access Branch '.Str::uuid(),
            'level' => 'SMP',
            'address' => 'Test address',
        ]);
        $employee = \App\Models\Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::uuid(),
            'name' => 'Payroll Access Employee',
            'type' => 'STAFF',
            'base_salary' => 1000000,
            'status' => 'TETAP',
        ]);
        $salary = \App\Models\Salary::query()->create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'period' => now()->format('Y-m'),
            'incentives' => 0,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 1000000,
            'status' => 'PENDING',
        ]);
        $branchFinance = $this->createUser('keuangan', $branch);

        Livewire::actingAs($branchFinance)
            ->test(PayrollManager::class)
            ->assertSee($employee->name)
            ->assertSee('Cetak slip pegawai')
            ->assertDontSee('Simpan perhitungan')
            ->call('savePayroll')
            ->assertForbidden();
        Livewire::actingAs($branchFinance)
            ->test(PayrollManager::class)
            ->call('markPaid', $salary->id)
            ->assertForbidden();
        $this->actingAs($branchFinance)
            ->get(route('payroll.export.xlsx', ['period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function createUser(string $role, ?Branch $branch = null): User
    {
        return User::query()->create([
            'name' => 'Central Finance Access '.Str::uuid(),
            'email' => Str::uuid().'@example.test',
            'password' => 'central-finance-access-password',
            'branch_id' => $branch?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

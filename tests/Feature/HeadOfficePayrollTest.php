<?php

namespace Tests\Feature;

use App\Livewire\IncentiveSettings;
use App\Livewire\PayrollManager;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\BranchIncentive;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HeadOfficePayrollTest extends TestCase
{
    use DatabaseTransactions;

    public function test_head_office_staff_can_be_paid_and_receive_central_incentives(): void
    {
        $headOffice = Branch::query()->where('level', 'YAYASAN')->firstOrFail();
        $admin = User::query()->create([
            'name' => 'Head Office Payroll Finance',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'branch_id' => $headOffice->id,
            'nip' => Str::uuid(),
            'name' => 'Yayasan Staff Payroll',
            'type' => 'STAFF',
            'base_salary' => 5000000,
            'status' => 'TETAP',
        ]);
        $centralAdmin = User::query()->create([
            'name' => 'Head Office View Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        Livewire::actingAs($centralAdmin)
            ->test(PayrollManager::class)
            ->set('branchId', $headOffice->id)
            ->assertDontSee('Hitung payroll')
            ->call('savePayroll')
            ->assertForbidden();

        Livewire::actingAs($centralAdmin)
            ->test(IncentiveSettings::class)
            ->set('branchId', $headOffice->id)
            ->set('monthlyTransportAllowance', '150000')
            ->set('substitutionAllowancePerPeriod', '0')
            ->call('saveBranchPayrollSettings')
            ->assertHasNoErrors();
        Livewire::actingAs($centralAdmin)
            ->test(IncentiveSettings::class)
            ->set('branchId', $headOffice->id)
            ->set('newIncentiveName', 'Insentif Kantor Pusat')
            ->set('newIncentiveType', 'MANUAL')
            ->set('newIncentiveDefault', '250000')
            ->call('addIncentiveType')
            ->assertHasNoErrors();
        $incentive = BranchIncentive::query()
            ->where('branch_id', $headOffice->id)
            ->where('name', 'Insentif Kantor Pusat')
            ->firstOrFail();
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $period = now()->format('Y-m');

        Livewire::actingAs($admin)
            ->test(PayrollManager::class)
            ->set('branchId', $headOffice->id)
            ->set('employeeId', $employee->id)
            ->set('period', $period)
            ->set('academicYearId', $academicYear->id)
            ->set("incentiveAmounts.{$incentive->id}", '400000')
            ->call('savePayroll')
            ->assertHasNoErrors()
            ->assertSee('Yayasan Pusat');

        $salary = Salary::query()
            ->where('branch_id', $headOffice->id)
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->firstOrFail();

        $this->assertSame('5550000.00', $salary->net_salary);
        $this->assertSame('400000.00', $salary->incentives);
        $this->assertSame('150000.00', $salary->allowances);
    }
}

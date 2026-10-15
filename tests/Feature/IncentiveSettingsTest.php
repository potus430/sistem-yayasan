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

class IncentiveSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_incentive_configuration_is_separate_and_annual_value_is_included_in_payroll(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Incentive Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Finance Incentive Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'keuangan',
            'is_active' => true,
        ]);
        $centralFinance = User::query()->create([
            'name' => 'Central Incentive Finance',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $centralAdmin = User::query()->create([
            'name' => 'Central Incentive Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Annual Incentive Employee',
            'type' => 'STAFF',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $academicYear = AcademicYear::query()->create([
            'name' => '2097/2098-'.Str::random(4),
            'starts_on' => '2097-07-01',
            'ends_on' => '2098-06-30',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('payroll'))
            ->assertOk()
            ->assertDontSee('Hitung payroll');
        $this->get(route('incentives'))
            ->assertOk()
            ->assertSee('Daftar jenis insentif cabang')
            ->assertSee('Tarif otomatis cabang');

        Livewire::actingAs($user)
            ->test(IncentiveSettings::class)
            ->assertDontSee('Tambah jenis insentif')
            ->call('addIncentiveType')
            ->assertForbidden();

        Livewire::actingAs($centralFinance)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->call('addIncentiveType')
            ->assertForbidden();

        Livewire::actingAs($centralAdmin)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->set('newIncentiveName', 'Tunjangan jabatan')
            ->set('newIncentiveType', 'ANNUAL')
            ->set('newIncentiveDefault', '50000')
            ->call('addIncentiveType')
            ->assertHasNoErrors();

        $incentive = BranchIncentive::query()
            ->where('branch_id', $branch->id)
            ->where('name', 'Tunjangan jabatan')
            ->firstOrFail();
        $this->assertSame('50000.00', $incentive->default_amount);
        Livewire::actingAs($user)
            ->test(IncentiveSettings::class)
            ->set("incentiveDefinitions.{$incentive->id}.default_amount", '100000')
            ->call('saveIncentiveType', $incentive->id)
            ->assertForbidden();
        $this->assertSame('50000.00', $incentive->fresh()->default_amount);

        Livewire::actingAs($centralFinance)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->set('employeeId', $employee->id)
            ->set('academicYearId', $academicYear->id)
            ->set("annualIncentiveAmounts.{$incentive->id}", '50000')
            ->call('saveAnnualIncentives')
            ->assertHasNoErrors();

        Livewire::actingAs($centralFinance)
            ->test(PayrollManager::class)
            ->set('branchId', $branch->id)
            ->set('employeeId', $employee->id)
            ->set('period', '2026-10')
            ->set('academicYearId', $academicYear->id)
            ->assertSee('Rp 650.000')
            ->call('savePayroll')
            ->assertHasNoErrors();

        $salary = Salary::query()
            ->where('branch_id', $branch->id)
            ->where('employee_id', $employee->id)
            ->where('period', '2026-10')
            ->firstOrFail();

        $this->assertSame('50000.00', $salary->incentives);
        $this->assertSame('650000.00', $salary->net_salary);
    }
}

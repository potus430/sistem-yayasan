<?php

namespace Tests\Feature;

use App\Livewire\IncentiveSettings;
use App\Livewire\PayrollManager;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\BranchIncentive;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\Salary;
use App\Models\TeacherHourlyRate;
use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AutomaticPayrollAllowancesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payroll_preview_and_salary_include_transport_and_recorded_substitutions(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Payroll Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
            'monthly_transport_allowance' => 50000,
            'substitution_allowance_per_period' => 15000,
        ]);
        $user = User::query()->create([
            'name' => 'Branch Finance Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'keuangan',
            'is_active' => true,
        ]);
        $centralFinance = User::query()->create([
            'name' => 'Central Finance Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $centralAdmin = User::query()->create([
            'name' => 'Central Admin Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $teacher = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Substitute Teacher',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $absentTeacher = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Absent Teacher',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $academicYear = AcademicYear::query()->create([
            'name' => '2098/2099-'.Str::random(4),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => false,
        ]);
        TeacherHourlyRate::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'amount' => 150000,
        ]);
        RosterSlot::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'employee_id' => $teacher->id,
            'subject' => 'Matematika',
            'class_name' => 'X-A',
            'room' => 'Ruang 1',
            'weekday' => 1,
            'period' => 1,
        ]);
        $manualIncentive = BranchIncentive::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Tunjangan Uji',
            'calculation_type' => 'MANUAL',
            'default_amount' => 25000,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3] as $period) {
            TeacherSubstitution::query()->create([
                'branch_id' => $branch->id,
                'roster_slot_id' => (string) Str::uuid(),
                'absent_employee_id' => $absentTeacher->id,
                'substitute_employee_id' => $teacher->id,
                'date' => '2026-10-'.sprintf('%02d', $period),
                'period' => $period,
                'is_cancelled' => $period === 3,
            ]);
        }

        Livewire::actingAs($user)
            ->test(IncentiveSettings::class)
            ->call('saveBranchPayrollSettings')
            ->assertForbidden();

        Livewire::actingAs($centralFinance)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->call('saveBranchPayrollSettings')
            ->assertForbidden();

        Livewire::actingAs($centralAdmin)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->set('monthlyTransportAllowance', '60000')
            ->set('substitutionAllowancePerPeriod', '20000')
            ->call('saveBranchPayrollSettings')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(PayrollManager::class)
            ->set('employeeId', $teacher->id)
            ->set('period', '2026-10')
            ->set('academicYearId', $academicYear->id)
            ->call('savePayroll')
            ->assertForbidden();

        Livewire::actingAs($centralFinance)
            ->test(PayrollManager::class)
            ->set('branchId', $branch->id)
            ->set('employeeId', $teacher->id)
            ->set('period', '2026-10')
            ->set('academicYearId', $academicYear->id)
            ->set("incentiveAmounts.{$manualIncentive->id}", '25000')
            ->assertSee('Invalen (2 jam)')
            ->assertSee('Rp 725.000')
            ->call('savePayroll')
            ->assertHasNoErrors();

        $salary = Salary::query()
            ->where('branch_id', $branch->id)
            ->where('employee_id', $teacher->id)
            ->where('period', '2026-10')
            ->firstOrFail();

        $this->assertSame('100000.00', $salary->allowances);
        $this->assertSame('725000.00', $salary->net_salary);
        $this->assertSame(2, collect($salary->allowance_details)->firstWhere('type', 'SUBSTITUTION')['count']);
        $this->assertEquals(20000, collect($salary->allowance_details)->firstWhere('type', 'SUBSTITUTION')['rate']);
    }
}

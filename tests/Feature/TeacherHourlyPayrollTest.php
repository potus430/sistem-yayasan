<?php

namespace Tests\Feature;

use App\Livewire\IncentiveSettings;
use App\Livewire\PayrollManager;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\Salary;
use App\Models\TeacherHourlyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherHourlyPayrollTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_admin_sets_branch_and_year_hourly_rate_and_teacher_salary_uses_month_roster_hours(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Teacher Hourly Branch '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $admin = $this->createUser('admin_pusat');
        $finance = $this->createUser('keuangan_pusat');
        $academicYear = AcademicYear::query()->create([
            'name' => '2026/2027-'.Str::random(4),
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-20',
            'is_active' => false,
        ]);
        $teacher = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Hourly Teacher',
            'type' => 'GURU',
            'base_salary' => 1000000,
            'status' => 'TETAP',
        ]);

        Livewire::actingAs($finance)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->set('teacherHourlyRate', '25000')
            ->call('saveTeacherHourlyRate')
            ->assertForbidden();
        $this->assertDatabaseMissing('branch_teacher_hourly_rates', [
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
        ]);

        Livewire::actingAs($admin)
            ->test(IncentiveSettings::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->set('teacherHourlyRate', '25000')
            ->call('saveTeacherHourlyRate')
            ->assertHasNoErrors();

        $rate = TeacherHourlyRate::query()
            ->where('branch_id', $branch->id)
            ->where('academic_year_id', $academicYear->id)
            ->firstOrFail();
        $this->assertSame('25000.00', $rate->amount);

        foreach ([[1, 1], [5, 2]] as [$weekday, $period]) {
            RosterSlot::query()->create([
                'branch_id' => $branch->id,
                'academic_year_id' => $academicYear->id,
                'employee_id' => $teacher->id,
                'subject' => 'Matematika',
                'class_name' => 'X-A',
                'room' => "Ruang {$period}",
                'weekday' => $weekday,
                'period' => $period,
            ]);
        }

        Livewire::actingAs($finance)
            ->test(PayrollManager::class)
            ->set('branchId', $branch->id)
            ->set('employeeId', $teacher->id)
            ->set('period', '2026-10')
            ->set('academicYearId', $academicYear->id)
            ->assertSee('Jam mengajar bulan ini')
            ->assertSee('5 jam')
            ->assertSee('Rp 125.000')
            ->call('savePayroll')
            ->assertHasNoErrors();

        $salary = Salary::query()
            ->where('employee_id', $teacher->id)
            ->where('period', '2026-10')
            ->firstOrFail();
        $this->assertSame(5, $salary->teaching_hours);
        $this->assertSame('25000.00', $salary->hourly_rate);
        $this->assertSame('125000.00', $salary->base_salary);
        $this->assertSame('125000.00', $salary->net_salary);
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'name' => 'Test '.$role,
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

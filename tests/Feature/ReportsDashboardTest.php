<?php

namespace Tests\Feature;

use App\Livewire\ReportsDashboard;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\SppBilling;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_admin_report_is_scoped_to_own_branch(): void
    {
        $branch = $this->createBranch('Branch Report');
        $otherBranch = $this->createBranch('Other Report');
        $user = User::query()->create([
            'name' => 'Branch Report Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);
        Student::query()->create(['branch_id' => $branch->id, 'nisn' => 'BR-'.Str::random(10), 'name' => 'Own Branch Student', 'current_class' => 'X-A']);
        Student::query()->create(['branch_id' => $otherBranch->id, 'nisn' => 'OT-'.Str::random(10), 'name' => 'Other Branch Student', 'current_class' => 'X-A']);

        Livewire::actingAs($user)
            ->test(ReportsDashboard::class)
            ->assertSee('Siswa terdaftar')
            ->assertSee('>1</p>', false)
            ->set('branchId', $otherBranch->id)
            ->assertSee('Branch Report')
            ->assertDontSee('Laporan SPP');
    }

    public function test_finance_report_shows_branch_scoped_payroll_and_spp_summaries(): void
    {
        $branch = $this->createBranch('Finance Report');
        $otherBranch = $this->createBranch('Other Finance');
        $year = AcademicYear::query()->create([
            'name' => '2097/2098-'.Str::random(4),
            'starts_on' => '2097-07-01',
            'ends_on' => '2098-06-30',
            'is_active' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Finance Report User',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'keuangan',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => 'FIN-'.Str::random(10),
            'name' => 'Finance Sample Employee',
            'type' => 'STAFF',
            'base_salary' => 500000,
            'status' => 'TETAP',
        ]);
        $otherEmployee = Employee::query()->create([
            'branch_id' => $otherBranch->id,
            'nip' => 'OTH-'.Str::random(10),
            'name' => 'Other Finance Employee',
            'type' => 'STAFF',
            'base_salary' => 900000,
            'status' => 'TETAP',
        ]);
        Salary::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'period' => now()->format('Y-m'),
            'incentives' => 0,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 500000,
            'status' => 'PAID',
        ]);
        Salary::query()->create([
            'employee_id' => $otherEmployee->id,
            'branch_id' => $otherBranch->id,
            'period' => now()->format('Y-m'),
            'incentives' => 0,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 900000,
            'status' => 'PAID',
        ]);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'SPP-'.Str::random(10),
            'name' => 'Finance Report Student',
            'current_class' => 'X-A',
        ]);
        SppBilling::query()->create([
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'month' => 1,
            'amount' => 400000,
            'paid_amount' => 150000,
            'status' => 'UNPAID',
        ]);

        Livewire::actingAs($user)
            ->test(ReportsDashboard::class)
            ->set('academicYearId', $year->id)
            ->assertSee('Payroll dibayar')
            ->assertSee('Rp 500.000')
            ->assertSee('Rp 150.000')
            ->assertSee('Rp 250.000')
            ->assertDontSee('Rp 900.000');
    }

    public function test_central_admin_can_view_branch_comparison_and_filter_a_branch(): void
    {
        $branch = $this->createBranch('Central Report');
        $otherBranch = $this->createBranch('Second Central Report');
        $user = User::query()->create([
            'name' => 'Central Report Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        Student::query()->create(['branch_id' => $branch->id, 'nisn' => 'C1-'.Str::random(8), 'name' => 'Central Student One', 'current_class' => 'X-A']);
        Student::query()->create(['branch_id' => $otherBranch->id, 'nisn' => 'C2-'.Str::random(8), 'name' => 'Central Student Two', 'current_class' => 'X-A']);

        Livewire::actingAs($user)
            ->test(ReportsDashboard::class)
            ->assertSee('Ikhtisar seluruh cabang')
            ->assertSee('Central Report')
            ->assertSee('Second Central Report')
            ->set('branchId', $branch->id)
            ->assertDontSee('Ikhtisar seluruh cabang');
    }

    private function createBranch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name.' '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\PayrollManager;
use App\Livewire\PositionCompensationManager;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchPosition;
use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\RosterSlot;
use App\Models\Salary;
use App\Models\SchoolClass;
use App\Models\TeacherHourlyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PositionCompensationPayrollTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_finance_manages_compensation_while_branch_admin_manages_existing_position_names(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Central Position Review '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $admin = User::query()->create([
            'name' => 'Central Position Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $centralFinance = User::query()->create([
            'name' => 'Central Position Finance',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Assigned Branch Employee',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $position = BranchPosition::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kepala Sekolah',
            'requires_class' => false,
            'monthly_salary_addition' => 100000,
            'monthly_allowance' => 50000,
            'monthly_incentive' => 25000,
            'is_active' => true,
        ]);
        $assignment = EmployeePositionAssignment::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'employee_id' => $employee->id,
            'branch_position_id' => $position->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->assertSee('Kepala Sekolah')
            ->assertSee('Lihat saja')
            ->assertSee('Tambah jabatan dinamis');

        $component
            ->set("positionInputs.{$position->id}.name", 'Unauthorized Renamed Position')
            ->set("positionInputs.{$position->id}.monthly_salary_addition", '200000')
            ->set("positionInputs.{$position->id}.monthly_allowance", '75000')
            ->set("positionInputs.{$position->id}.monthly_incentive", '50000')
            ->call('savePosition', $position->id)
            ->assertForbidden();

        $this->assertDatabaseHas('branch_positions', [
            'id' => $position->id,
            'name' => 'Kepala Sekolah',
            'requires_class' => false,
            'monthly_salary_addition' => 100000,
            'monthly_allowance' => 50000,
            'monthly_incentive' => 25000,
        ]);

        Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->set('newPositionName', 'Koordinator Kurikulum')
            ->set('newPositionRequiresClass', false)
            ->call('addPosition')
            ->assertHasNoErrors();

        Livewire::actingAs($centralFinance)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->set("positionInputs.{$position->id}.monthly_salary_addition", '200000')
            ->set("positionInputs.{$position->id}.monthly_allowance", '75000')
            ->set("positionInputs.{$position->id}.monthly_incentive", '50000')
            ->call('savePosition', $position->id)
            ->assertHasNoErrors();
        Livewire::actingAs($centralFinance)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $academicYear->id)
            ->set('newPositionName', 'Koordinator Kurikulum')
            ->set('newPositionRequiresClass', false)
            ->call('addPosition')
            ->assertForbidden();
        $newPosition = BranchPosition::query()
            ->where('branch_id', $branch->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('name', 'Koordinator Kurikulum')
            ->firstOrFail();
        $this->assertSame('0.00', $newPosition->monthly_salary_addition);
        Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->call('togglePosition', $position->id)
            ->assertForbidden();
        Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->call('deletePosition', $position->id)
            ->assertForbidden();
        Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->call('assignPosition')
            ->assertForbidden();
        Livewire::actingAs($admin)
            ->test(PositionCompensationManager::class)
            ->set('branchId', $branch->id)
            ->call('removeAssignment', $assignment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('employee_position_assignments', ['id' => $assignment->id]);

        $branchAdmin = User::query()->create([
            'name' => 'Branch Position Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);
        Livewire::actingAs($branchAdmin)
            ->test(PositionCompensationManager::class)
            ->set('newPositionName', 'Unauthorized Dynamic Position')
            ->set('newPositionRequiresClass', false)
            ->call('addPosition')
            ->assertForbidden();
        $this->assertDatabaseMissing('branch_positions', [
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Unauthorized Dynamic Position',
        ]);
        Livewire::actingAs($branchAdmin)
            ->test(PositionCompensationManager::class)
            ->set("positionInputs.{$position->id}.name", 'Koordinator Operasional')
            ->set("positionInputs.{$position->id}.requires_class", false)
            ->set("positionInputs.{$position->id}.monthly_salary_addition", '999999')
            ->set("positionInputs.{$position->id}.monthly_allowance", '999999')
            ->set("positionInputs.{$position->id}.monthly_incentive", '999999')
            ->call('savePosition', $position->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branch_positions', [
            'id' => $position->id,
            'name' => 'Koordinator Operasional',
            'monthly_salary_addition' => 200000,
            'monthly_allowance' => 75000,
            'monthly_incentive' => 50000,
        ]);
        Livewire::actingAs($branchAdmin)
            ->test(PositionCompensationManager::class)
            ->call('deletePosition', $position->id)
            ->assertHasErrors(["positionInputs.{$position->id}.delete"]);

        Livewire::actingAs($branchAdmin)
            ->test(PositionCompensationManager::class)
            ->call('deletePosition', $newPosition->id)
            ->assertHasNoErrors();
        $this->assertDatabaseMissing('branch_positions', ['id' => $newPosition->id]);
    }

    public function test_assignment_and_position_compensation_are_snapshotted_in_payroll(): void
    {
        AcademicYear::query()->update(['is_active' => false]);
        $branch = Branch::query()->create([
            'name' => 'Position Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Branch Admin Position Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);
        $centralFinance = User::query()->create([
            'name' => 'Payroll Central Finance',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Position Compensation Employee',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $academicYear = AcademicYear::query()->create([
            'name' => '2099/2100-'.Str::random(4),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        TeacherHourlyRate::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'amount' => 150000,
        ]);
        RosterSlot::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'employee_id' => $employee->id,
            'subject' => 'Matematika',
            'class_name' => '10 IPA',
            'room' => 'Ruang 1',
            'weekday' => 1,
            'period' => 1,
        ]);
        SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => '10 IPA']);
        SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => '11 IPA']);
        $position = BranchPosition::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kepala Sekolah',
            'requires_class' => false,
            'monthly_salary_addition' => 100000,
            'monthly_allowance' => 50000,
            'monthly_incentive' => 25000,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $position->id)
            ->set('employeeId', $employee->id)
            ->call('assignPosition')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_position_assignments', [
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'employee_id' => $employee->id,
            'branch_position_id' => $position->id,
            'class_name' => null,
        ]);

        Livewire::actingAs($centralFinance)
            ->test(PayrollManager::class)
            ->set('branchId', $branch->id)
            ->set('employeeId', $employee->id)
            ->set('period', '2026-10')
            ->set('academicYearId', $academicYear->id)
            ->assertSee('Tambahan gaji jabatan')
            ->assertSee('Rp 775.000')
            ->call('savePayroll')
            ->assertHasNoErrors();

        $salary = Salary::query()
            ->where('branch_id', $branch->id)
            ->where('employee_id', $employee->id)
            ->where('period', '2026-10')
            ->firstOrFail();

        $this->assertSame('100000.00', $salary->position_salary);
        $this->assertSame('775000.00', $salary->net_salary);
        $this->assertSame('Kepala Sekolah', $salary->position_details[0]['name']);
        $this->assertEquals(50000, collect($salary->allowance_details)->firstWhere('type', 'POSITION')['amount']);
        $this->assertEquals(25000, collect($salary->incentive_details)->firstWhere('type', 'POSITION')['amount']);
        $this->assertSame(1, EmployeePositionAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('academic_year_id', $academicYear->id)
            ->count());

        $vicePrincipal = BranchPosition::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'academic_year_id' => $academicYear->id,
                'name' => 'Wakil Kepala Sekolah',
            ],
            [
                'requires_class' => false,
                'monthly_salary_addition' => 0,
                'monthly_allowance' => 0,
                'monthly_incentive' => 0,
                'is_active' => true,
            ]
        );
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $vicePrincipal->id)
            ->set('employeeId', $employee->id)
            ->call('assignPosition')
            ->assertHasErrors('employeeId');

        $homeroomPosition = BranchPosition::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'academic_year_id' => $academicYear->id,
                'name' => 'Wali Kelas',
            ],
            [
                'requires_class' => true,
                'monthly_salary_addition' => 0,
                'monthly_allowance' => 0,
                'monthly_incentive' => 0,
                'is_active' => true,
            ]
        );
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $homeroomPosition->id)
            ->set('employeeId', $employee->id)
            ->set('className', '10 IPA')
            ->call('assignPosition')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $homeroomPosition->id)
            ->set('employeeId', $employee->id)
            ->set('className', '11 IPA')
            ->call('assignPosition')
            ->assertHasErrors('employeeId');

        $anotherEmployee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Second Position Employee',
            'type' => 'STAFF',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $position->id)
            ->set('employeeId', $anotherEmployee->id)
            ->call('assignPosition')
            ->assertHasErrors('positionId');

        $anotherTeacher = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Another Homeroom Teacher',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $homeroomPosition->id)
            ->set('employeeId', $anotherTeacher->id)
            ->set('className', '10 IPA')
            ->call('assignPosition')
            ->assertHasErrors('className');

        $otherClassPosition = BranchPosition::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Penugasan Kelas Khusus',
            'requires_class' => true,
            'monthly_salary_addition' => 0,
            'monthly_allowance' => 0,
            'monthly_incentive' => 0,
            'is_active' => true,
        ]);
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $otherClassPosition->id)
            ->set('employeeId', $anotherTeacher->id)
            ->set('className', '10 IPA')
            ->call('assignPosition')
            ->assertHasErrors('className');

        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $academicYear->id)
            ->set('positionId', $homeroomPosition->id)
            ->set('employeeId', $anotherEmployee->id)
            ->set('className', '11 IPA')
            ->call('assignPosition')
            ->assertHasErrors('employeeId');

        $inactiveYear = AcademicYear::query()->create([
            'name' => '2098/2099-'.Str::random(4),
            'starts_on' => '2098-07-01',
            'ends_on' => '2099-06-30',
            'is_active' => false,
        ]);
        Livewire::actingAs($user)
            ->test(PositionCompensationManager::class)
            ->set('academicYearId', $inactiveYear->id)
            ->assertHasErrors('academicYearId');
    }

    public function test_position_entities_and_payroll_snapshots_can_be_ingested_from_a_branch(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Position Sync Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::random(15),
            'name' => 'Synced Position Employee',
            'type' => 'GURU',
            'base_salary' => 600000,
            'status' => 'TETAP',
        ]);
        $academicYear = AcademicYear::query()->create([
            'name' => '2100/2101-'.Str::random(4),
            'starts_on' => '2100-07-01',
            'ends_on' => '2101-06-30',
            'is_active' => false,
        ]);
        $positionId = (string) Str::uuid();
        $assignmentId = (string) Str::uuid();
        $salaryId = (string) Str::uuid();
        $timestamp = now()->toDateTimeString();
        config(['sync.api_token' => 'position-sync-test-token']);

        $this->withToken('position-sync-test-token')
            ->postJson('/api/sync/branch_positions', [
                'records' => [[
                    'id' => $positionId,
                    'branch_id' => $branch->id,
                    'academic_year_id' => $academicYear->id,
                    'name' => 'Kepala Sekolah',
                    'requires_class' => false,
                    'monthly_salary_addition' => 100000,
                    'monthly_allowance' => 50000,
                    'monthly_incentive' => 25000,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);

        $this->withToken('position-sync-test-token')
            ->postJson('/api/sync/employee_position_assignments', [
                'records' => [[
                    'id' => $assignmentId,
                    'branch_id' => $branch->id,
                    'academic_year_id' => $academicYear->id,
                    'employee_id' => $employee->id,
                    'branch_position_id' => $positionId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);

        $this->withToken('position-sync-test-token')
            ->postJson('/api/sync/salaries', [
                'records' => [[
                    'id' => $salaryId,
                    'employee_id' => $employee->id,
                    'branch_id' => $branch->id,
                    'period' => '2026-10',
                    'incentives' => 25000,
                    'incentive_details' => [[
                        'position_id' => $positionId,
                        'name' => 'Insentif jabatan · Kepala Sekolah',
                        'amount' => 25000,
                        'type' => 'POSITION',
                    ]],
                    'position_salary' => 100000,
                    'position_details' => [[
                        'position_id' => $positionId,
                        'name' => 'Kepala Sekolah',
                        'class_name' => null,
                        'salary_addition' => 100000,
                        'allowance' => 50000,
                        'incentive' => 25000,
                    ]],
                    'allowances' => 50000,
                    'allowance_details' => [[
                        'name' => 'Tunjangan jabatan · Kepala Sekolah',
                        'type' => 'POSITION',
                        'position_id' => $positionId,
                        'amount' => 50000,
                    ]],
                    'deductions' => 0,
                    'net_salary' => 775000,
                    'status' => 'PENDING',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);

        $this->assertDatabaseHas('employee_position_assignments', [
            'id' => $assignmentId,
            'branch_position_id' => $positionId,
            'employee_id' => $employee->id,
        ]);
        $this->assertSame('Integrasi API', AuditLog::query()
            ->where('auditable_type', BranchPosition::class)
            ->where('auditable_id', $positionId)
            ->where('event', 'created')
            ->sole()
            ->actor_name);
        $this->assertSame('100000.00', Salary::query()->findOrFail($salaryId)->position_salary);
    }
}

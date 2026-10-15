<?php

namespace Tests\Feature;

use App\Livewire\AttendanceManager;
use App\Livewire\EmployeeDirectory;
use App\Livewire\RosterGenerator;
use App\Livewire\SchoolStructureManager;
use App\Livewire\StudentDirectory;
use App\Livewire\UserDirectory;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\SchoolClass;
use App\Models\SchoolRoom;
use App\Models\SchoolSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_admin_can_manage_staff_but_not_teacher_records(): void
    {
        $branch = $this->createBranch();
        $centralAdmin = $this->createUser('admin_pusat');
        $centralFinance = $this->createUser('keuangan_pusat');
        $teacher = $this->createEmployee($branch, 'GURU', 'Branch Teacher');
        $staff = $this->createEmployee($branch, 'STAFF', 'Branch Staff');

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->set('search', 'Branch Staff')
            ->assertSee('Branch Staff')
            ->assertDontSee('Branch Teacher');

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->set('search', 'Branch Teacher')
            ->assertSee('Branch Teacher')
            ->call('editEmployee', $teacher->id)
            ->assertForbidden();

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->call('deleteEmployee', $teacher->id)
            ->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $teacher->id]);

        Livewire::actingAs($centralFinance)
            ->test(EmployeeDirectory::class)
            ->set("salaryAmounts.{$teacher->id}", '1750000')
            ->call('saveBaseSalary', $teacher->id)
            ->assertForbidden();
        $this->assertSame('1000000.00', $teacher->fresh()->base_salary);

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->set('formBranchId', $branch->id)
            ->set('formType', 'GURU')
            ->set('formNip', 'CENTRAL-TEACHER')
            ->set('formName', 'Unauthorized Teacher')
            ->set('formBaseSalary', '1000000')
            ->set('formStatus', 'TETAP')
            ->call('saveEmployee')
            ->assertHasErrors(['formType']);

        $this->assertDatabaseMissing('employees', ['nip' => 'CENTRAL-TEACHER']);

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->set('formBranchId', $branch->id)
            ->set('formType', 'STAFF')
            ->set('formNip', 'CENTRAL-STAFF')
            ->set('formName', 'New Branch Staff')
            ->set('formBaseSalary', '1000000')
            ->set('formStatus', 'TETAP')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'branch_id' => $branch->id,
            'nip' => 'CENTRAL-STAFF',
            'type' => 'STAFF',
        ]);
        $this->assertSame('STAFF', $staff->fresh()->type);
    }

    public function test_only_branch_admin_can_manage_students_and_teacher_records(): void
    {
        $branch = $this->createBranch();
        $centralAdmin = $this->createUser('admin_pusat');
        $branchAdmin = $this->createUser('admin_cabang', $branch);

        Livewire::actingAs($branchAdmin)
            ->test(EmployeeDirectory::class)
            ->call('createEmployee')
            ->set('formNip', 'TEACHER-001')
            ->set('formName', 'Branch Teacher')
            ->set('formType', 'GURU')
            ->set('formBaseSalary', '1000000')
            ->set('formStatus', 'TETAP')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        SchoolClass::query()->create([
            'branch_id' => $branch->id,
            'name' => '1A',
        ]);
        Livewire::actingAs($branchAdmin)
            ->test(StudentDirectory::class)
            ->call('createStudent')
            ->set('formNisn', 'STUDENT-001')
            ->set('formName', 'Branch Student')
            ->set('formCurrentClass', '1A')
            ->call('saveStudent')
            ->assertHasNoErrors();

        $this->actingAs($centralAdmin)->get(route('students'))->assertOk();
        Livewire::actingAs($centralAdmin)
            ->test(StudentDirectory::class)
            ->set('branchId', $branch->id)
            ->set('search', 'Branch Student')
            ->assertSee('Branch Student')
            ->call('createStudent')
            ->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'branch_id' => $branch->id,
            'nip' => 'TEACHER-001',
            'type' => 'GURU',
            'base_salary' => 0,
        ]);
        $teacher = Employee::query()->where('nip', 'TEACHER-001')->firstOrFail();
        Livewire::actingAs($branchAdmin)
            ->test(EmployeeDirectory::class)
            ->call('editEmployee', $teacher->id)
            ->set('formBaseSalary', '5000000')
            ->call('saveEmployee')
            ->assertHasNoErrors();
        $this->assertSame('0.00', $teacher->fresh()->base_salary);
        $this->assertDatabaseHas('students', [
            'branch_id' => $branch->id,
            'nisn' => 'STUDENT-001',
        ]);
    }

    public function test_central_admin_can_view_school_operations_but_cannot_change_them(): void
    {
        $branch = $this->createBranch();
        $centralAdmin = $this->createUser('admin_pusat');
        $teacher = $this->createEmployee($branch, 'GURU', 'Read Only Teacher');
        $class = SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => 'Read Only Class']);
        SchoolRoom::query()->create(['branch_id' => $branch->id, 'name' => 'Room 101']);
        SchoolSubject::query()->create(['branch_id' => $branch->id, 'name' => 'Read Only Subject']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        RosterSlot::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'employee_id' => $teacher->id,
            'subject' => 'Read Only Subject',
            'class_name' => $class->name,
            'room' => 'Room 101',
            'weekday' => 1,
            'period' => 1,
        ]);
        Attendance::query()->create([
            'branch_id' => $branch->id,
            'employee_id' => $teacher->id,
            'date' => now()->toDateString(),
            'status' => 'IZIN',
        ]);

        Livewire::actingAs($centralAdmin)
            ->test(SchoolStructureManager::class)
            ->set('branchId', $branch->id)
            ->assertSee('Read Only Class')
            ->call('create', 'class')
            ->assertForbidden();
        Livewire::actingAs($centralAdmin)
            ->test(AttendanceManager::class)
            ->set('branchId', $branch->id)
            ->assertSee('Read Only Teacher')
            ->call('saveAttendance')
            ->assertForbidden();
        Livewire::actingAs($centralAdmin)
            ->test(RosterGenerator::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $year->id)
            ->assertSee('Read Only Subject')
            ->call('generate')
            ->assertForbidden();
    }

    public function test_central_admin_can_create_profile_only_central_staff_account(): void
    {
        $centralAdmin = $this->createUser('admin_pusat');
        Livewire::actingAs($centralAdmin)
            ->test(UserDirectory::class)
            ->call('create')
            ->set('name', 'Foundation Staff')
            ->set('email', 'foundation.'.Str::uuid().'@example.test')
            ->set('role', 'staff_pusat')
            ->set('branchId', '')
            ->set('password', 'foundation-staff-password')
            ->set('isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $staff = User::query()->where('role', 'staff_pusat')->latest('created_at')->firstOrFail();
        $this->assertNull($staff->branch_id);

        $this->actingAs($staff)->get(route('profile'))->assertOk();
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('employees'))->assertForbidden();
        $this->get(route('students'))->assertForbidden();
    }

    public function test_central_admin_can_add_staff_to_yayasan_head_office_payroll_unit(): void
    {
        $headOffice = Branch::query()->where('level', 'YAYASAN')->firstOrFail();
        $centralAdmin = $this->createUser('admin_pusat');
        $centralFinance = $this->createUser('keuangan_pusat');

        Livewire::actingAs($centralAdmin)
            ->test(EmployeeDirectory::class)
            ->set('branchId', $headOffice->id)
            ->call('createEmployee')
            ->assertSee('Yayasan Pusat')
            ->set('formNip', 'HQ-STAFF-001')
            ->set('formName', 'Staff Kantor Pusat')
            ->set('formType', 'STAFF')
            ->set('formBaseSalary', '6500000')
            ->set('formStatus', 'TETAP')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'branch_id' => $headOffice->id,
            'nip' => 'HQ-STAFF-001',
            'type' => 'STAFF',
            'base_salary' => 0,
        ]);
        $employee = Employee::query()->where('nip', 'HQ-STAFF-001')->firstOrFail();
        Livewire::actingAs($centralFinance)
            ->test(EmployeeDirectory::class)
            ->set('branchId', $headOffice->id)
            ->set("salaryAmounts.{$employee->id}", '6500000')
            ->call('saveBaseSalary', $employee->id)
            ->assertHasNoErrors();
        $this->assertSame('6500000.00', $employee->fresh()->base_salary);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'name' => 'Staff Access Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
    }

    private function createUser(string $role, ?Branch $branch = null): User
    {
        return User::query()->create([
            'name' => 'Staff Access '.Str::uuid(),
            'email' => Str::uuid().'@example.test',
            'password' => 'staff-access-test-password',
            'branch_id' => $branch?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createEmployee(Branch $branch, string $type, string $name): Employee
    {
        return Employee::query()->create([
            'branch_id' => $branch->id,
            'nip' => Str::uuid(),
            'name' => $name,
            'type' => $type,
            'base_salary' => 1000000,
            'status' => 'TETAP',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\SyncDataToCentral;
use App\Livewire\AdmissionsManager;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\Branch;
use App\Models\ClassCapacity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionsManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_admin_can_process_application_and_acceptance_creates_student_and_class(): void
    {
        $branch = $this->createBranch('Admission Branch');
        $branchAdmin = $this->createUser('admin_cabang', $branch);
        $centralAdmin = $this->createUser('admin_pusat');
        $otherBranch = $this->createBranch('Other Admission Branch');
        $otherAdmin = $this->createUser('admin_cabang', $otherBranch);
        AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);
        $academicYear = AcademicYear::query()->create([
            'name' => 'PPDB 26/27 '.Str::random(4),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->actingAs($branchAdmin)->get(route('admissions'))->assertOk()->assertSee('Pendaftaran siswa');
        $this->actingAs($centralAdmin)->get(route('admissions'))->assertForbidden();

        $component = Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('createApplication')
            ->set('formAcademicYearId', $academicYear->id)
            ->set('formName', 'Calon Siswa')
            ->set('formGender', 'PEREMPUAN')
            ->set('formBirthPlace', 'Bandung')
            ->set('formBirthDate', '2014-04-12')
            ->set('formGuardianName', 'Orang Tua')
            ->set('formGuardianPhone', '08123456789')
            ->set('formAddress', 'Jl. Pendidikan No. 1')
            ->set('formPreviousSchool', 'TK Harapan')
            ->set('formAppliedClass', 'Kelas 1B')
            ->call('saveApplication')
            ->assertHasNoErrors();

        $application = AdmissionApplication::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('REGISTERED', $application->status);
        $this->assertStringStartsWith('PPDB-', $application->registration_number);

        Livewire::actingAs($otherAdmin)
            ->test(AdmissionsManager::class)
            ->assertDontSee('Calon Siswa');

        Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $application->id, 'PASSED')
            ->assertHasNoErrors();
        $this->assertSame('PASSED', $application->fresh()->status);

        Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $application->id, 'ACCEPTED')
            ->assertHasErrors(['application-'.$application->id]);
        $this->assertSame('PASSED', $application->fresh()->status);
        $this->assertDatabaseMissing('students', ['branch_id' => $branch->id, 'name' => 'Calon Siswa']);

        Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('openCapacityForm')
            ->set('capacityClassName', 'Kelas 1B')
            ->set('capacityValue', '2')
            ->call('createClassCapacity')
            ->assertHasNoErrors();

        Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('editApplication', $application->id)
            ->set('formNisn', '1234567890')
            ->call('saveApplication')
            ->assertHasNoErrors();
        $application->refresh();

        Livewire::actingAs($branchAdmin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $application->id, 'ACCEPTED')
            ->assertHasNoErrors()
            ->assertSee('Data siswa dan kelas berhasil dibuat');

        $student = Student::query()->where('branch_id', $branch->id)->where('nisn', '1234567890')->firstOrFail();
        $this->assertSame('Calon Siswa', $student->name);
        $this->assertSame('Kelas 1B', $student->current_class);
        $this->assertSame($academicYear->id, $student->admission_academic_year_id);
        $this->assertSame('NEW', $student->enrollment_type);
        $this->assertSame('PEREMPUAN', $student->gender);
        $this->assertSame('Orang Tua', $student->guardian_name);
        $this->assertSame('08123456789', $student->guardian_phone);
        $this->assertSame('Jl. Pendidikan No. 1', $student->address);
        $this->assertSame('TK Harapan', $student->previous_school);
        $this->assertSame($student->id, $application->fresh()->student_id);
        $this->assertDatabaseHas('school_classes', ['branch_id' => $branch->id, 'name' => 'Kelas 1B']);
        $this->assertNull($student->synced_at);
    }

    public function test_class_capacity_blocks_acceptance_when_full_and_allows_transfer_when_space_exists(): void
    {
        $branch = $this->createBranch('Capacity Transfer Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);
        $year = AcademicYear::query()->create([
            'name' => 'CAP '.Str::random(4),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => 'Kelas 4A']);
        ClassCapacity::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'class_name' => 'Kelas 4A',
            'capacity' => 1,
        ]);
        Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => '1111111111',
            'name' => 'Existing Student',
            'current_class' => 'Kelas 4A',
        ]);
        $schoolClass = SchoolClass::query()->where('branch_id', $branch->id)->where('name', 'Kelas 4A')->firstOrFail();
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('openCapacityForm')
            ->set("capacityInputs.{$schoolClass->id}", '0')
            ->call('saveClassCapacity', $schoolClass->id)
            ->assertHasErrors(["capacityInputs.{$schoolClass->id}"]);
        $this->assertSame(1, ClassCapacity::query()->where('branch_id', $branch->id)->where('class_name', 'Kelas 4A')->value('capacity'));
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('createApplication')
            ->set('formName', 'New Admission Candidate')
            ->set('formNisn', '3333333333')
            ->set('formGuardianName', 'New Guardian')
            ->set('formGuardianPhone', '08123456789')
            ->set('formAddress', 'New address')
            ->set('formAppliedClass', 'Kelas 4A')
            ->call('saveApplication')
            ->assertHasNoErrors();
        $admission = AdmissionApplication::query()->where('branch_id', $branch->id)->where('type', 'ADMISSION')->firstOrFail();
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $admission->id, 'PASSED')
            ->assertHasNoErrors();
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->assertSee('0 kursi tersedia')
            ->assertDontSee('Terima & buat siswa');
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $admission->id, 'ACCEPTED')
            ->assertHasErrors(['application-'.$admission->id]);
        $this->assertDatabaseMissing('students', ['nisn' => '3333333333']);

        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('createApplication', 'TRANSFER')
            ->set('formName', 'Transfer Candidate')
            ->set('formNisn', '2222222222')
            ->set('formGuardianName', 'Transfer Guardian')
            ->set('formGuardianPhone', '08123456789')
            ->set('formAddress', 'Transfer address')
            ->set('formPreviousSchool', 'Sekolah Asal')
            ->set('formAppliedClass', 'Kelas 4A')
            ->call('saveApplication')
            ->assertHasNoErrors();
        $transfer = AdmissionApplication::query()->where('branch_id', $branch->id)->where('type', 'TRANSFER')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $transfer->id, 'ACCEPTED')
            ->assertHasErrors(['application-'.$transfer->id]);
        $this->assertDatabaseMissing('students', ['nisn' => '2222222222']);

        ClassCapacity::query()->whereKey(
            ClassCapacity::query()->where('branch_id', $branch->id)->where('class_name', 'Kelas 4A')->value('id')
        )->update(['capacity' => 2]);
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $transfer->id, 'ACCEPTED')
            ->assertHasNoErrors();
        $transferStudent = Student::query()->where('nisn', '2222222222')->firstOrFail();
        $this->assertSame('TRANSFER', $transferStudent->enrollment_type);
        $this->assertSame('Sekolah Asal', $transferStudent->previous_school);
        $this->assertSame('ACCEPTED', $transfer->fresh()->status);
    }

    public function test_capacity_cannot_be_reduced_below_current_class_enrollment(): void
    {
        $branch = $this->createBranch('Capacity Reduction Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = SchoolClass::query()->create(['branch_id' => $branch->id, 'name' => 'Kelas Penuh']);
        $capacity = ClassCapacity::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'class_name' => $schoolClass->name,
            'capacity' => 2,
        ]);
        foreach (['4444444444', '5555555555'] as $index => $nisn) {
            Student::query()->create([
                'branch_id' => $branch->id,
                'nisn' => $nisn,
                'name' => 'Student '.$index,
                'current_class' => $schoolClass->name,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('openCapacityForm')
            ->set("capacityInputs.{$schoolClass->id}", '1')
            ->call('saveClassCapacity', $schoolClass->id)
            ->assertHasErrors(["capacityInputs.{$schoolClass->id}"]);

        $this->assertSame(2, $capacity->fresh()->capacity);
    }

    public function test_sync_job_sends_school_classes_before_accepted_students(): void
    {
        $branch = $this->createBranch('Admission Sync Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('createApplication')
            ->set('formName', 'Synced Student')
            ->set('formNisn', '9988776655')
            ->set('formGuardianName', 'Guardian')
            ->set('formGuardianPhone', '0811222333')
            ->set('formAddress', 'Sync address')
            ->set('formAppliedClass', 'New Sync Class')
            ->call('saveApplication')
            ->assertHasNoErrors();
        $application = AdmissionApplication::query()->where('branch_id', $branch->id)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('openCapacityForm')
            ->set('capacityClassName', 'New Sync Class')
            ->set('capacityValue', '20')
            ->call('createClassCapacity')
            ->assertHasNoErrors();
        Livewire::actingAs($admin)
            ->test(AdmissionsManager::class)
            ->call('advanceApplication', $application->id, 'PASSED')
            ->call('advanceApplication', $application->id, 'ACCEPTED')
            ->assertHasNoErrors();

        config(['sync.central_url' => 'https://central.example.test', 'sync.api_token' => 'test-sync-token']);
        $sentEntities = [];
        Http::fake(function ($request) use (&$sentEntities) {
            $sentEntities[] = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response(['accepted' => count($request['records'] ?? [])], 200);
        });

        app(SyncDataToCentral::class)->handle();

        $classIndex = array_search('school_classes', $sentEntities, true);
        $capacityIndex = array_search('class_capacities', $sentEntities, true);
        $studentIndex = array_search('students', $sentEntities, true);
        $this->assertIsInt($classIndex);
        $this->assertIsInt($capacityIndex);
        $this->assertIsInt($studentIndex);
        $this->assertLessThan($capacityIndex, $classIndex);
        $this->assertLessThan($studentIndex, $capacityIndex);
        $this->assertLessThan($studentIndex, $classIndex);
        $this->assertNotNull(Student::query()->where('nisn', '9988776655')->firstOrFail()->fresh()->synced_at);
    }

    public function test_central_sync_endpoint_accepts_class_data_for_a_branch(): void
    {
        $branch = $this->createBranch('Central Class Sync Branch');
        $academicYear = AcademicYear::query()->create([
            'name' => 'SYNC '.Str::random(4),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => false,
        ]);
        $existingClass = SchoolClass::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Kelas Sinkron',
        ]);
        config(['sync.api_token' => 'test-sync-token']);
        $now = now()->toDateTimeString();

        $this->withToken('test-sync-token')
            ->postJson('/api/sync/school_classes', [
                'records' => [[
                    'id' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Kelas Sinkron',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);

        $this->assertDatabaseHas('school_classes', [
            'id' => $existingClass->id,
            'branch_id' => $branch->id,
            'name' => 'Kelas Sinkron',
        ]);
        $this->assertSame(1, SchoolClass::query()->where('branch_id', $branch->id)->where('name', 'Kelas Sinkron')->count());

        $this->withToken('test-sync-token')
            ->postJson('/api/sync/class_capacities', [
                'records' => [[
                    'id' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'academic_year_id' => $academicYear->id,
                    'class_name' => 'Kelas Sinkron',
                    'capacity' => 28,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);
        $this->assertDatabaseHas('class_capacities', [
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'class_name' => 'Kelas Sinkron',
            'capacity' => 28,
        ]);

        $studentId = (string) Str::uuid();
        $this->withToken('test-sync-token')
            ->postJson('/api/sync/students', [
                'records' => [[
                    'id' => $studentId,
                    'branch_id' => $branch->id,
                    'nisn' => '8877665544',
                    'name' => 'Synced Student',
                    'current_class' => 'Kelas Sinkron',
                    'gender' => 'LAKI_LAKI',
                    'guardian_name' => 'Guardian Synced',
                    'guardian_phone' => '08123456789',
                    'address' => 'Synced address',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);
        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'branch_id' => $branch->id,
            'guardian_name' => 'Guardian Synced',
            'address' => 'Synced address',
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name.' '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
    }

    private function createUser(string $role, ?Branch $branch = null): User
    {
        return User::query()->create([
            'name' => 'Test '.$role,
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

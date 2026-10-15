<?php

namespace Tests\Feature;

use App\Livewire\SchoolStructureManager;
use App\Livewire\SppDashboard;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\SchoolSubject;
use App\Models\SppBilling;
use App\Models\SppClassFee;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_model_create_update_and_delete_are_logged_with_actor_ip_and_browser(): void
    {
        $admin = User::query()->create([
            'name' => 'Audit Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'correct-horse-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'name' => 'Audit Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);

        $this->actingAs($admin)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.25',
                'HTTP_USER_AGENT' => 'AuditTestBrowser/1.0 (Windows 11)',
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $subject = SchoolSubject::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Matematika',
        ]);
        $subject->update(['name' => 'Aljabar']);
        $subject->delete();

        $logs = AuditLog::query()
            ->where('auditable_type', SchoolSubject::class)
            ->where('auditable_id', $subject->id)
            ->orderBy('created_at')
            ->get()
            ->keyBy('event');

        $this->assertCount(3, $logs);
        $this->assertSame('Audit Admin', $logs['created']->actor_name);
        $this->assertSame($admin->id, $logs['created']->actor_user_id);
        $this->assertSame('203.0.113.25', $logs['created']->ip_address);
        $this->assertSame('AuditTestBrowser/1.0 (Windows 11)', $logs['created']->user_agent);
        $this->assertSame('Matematika', $logs['updated']->old_values['name']);
        $this->assertSame('Aljabar', $logs['updated']->new_values['name']);
        $this->assertSame('Aljabar', $logs['deleted']->old_values['name']);

        $this->get(route('activity-logs'))
            ->assertOk()
            ->assertSee('Log Aktivitas')
            ->assertSee('Audit Admin')
            ->assertSee('203.0.113.25')
            ->assertSee('AuditTestBrowser/1.0');
    }

    public function test_sensitive_credentials_are_not_saved_in_audit_snapshots(): void
    {
        $admin = User::query()->create([
            'name' => 'Credential Audit Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'correct-horse-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $this->actingAs($admin);

        $user = User::query()->create([
            'name' => 'New User',
            'email' => Str::uuid().'@example.test',
            'password' => 'never-store-this-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);

        $snapshot = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'created')
            ->sole()
            ->new_values;

        $this->assertArrayNotHasKey('password', $snapshot);
        $this->assertArrayNotHasKey('remember_token', $snapshot);
    }

    public function test_bulk_generated_spp_bills_are_logged_once_per_created_record(): void
    {
        $branch = Branch::query()->create([
            'name' => 'SPP Audit Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $finance = User::query()->create([
            'name' => 'SPP Audit Finance',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);
        $year = AcademicYear::query()->create([
            'name' => '2100/2101-'.Str::random(5),
            'starts_on' => '2100-07-01',
            'ends_on' => '2101-06-30',
            'is_active' => false,
        ]);
        $class = SchoolClass::query()->create([
            'branch_id' => $branch->id,
            'name' => '1A',
        ]);
        Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => Str::random(12),
            'name' => 'Audit Student',
            'current_class' => $class->name,
        ]);
        SppClassFee::query()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'amount' => 125000,
        ]);

        $component = Livewire::actingAs($finance)
            ->test(SppDashboard::class)
            ->set('branchId', $branch->id)
            ->set('academicYearId', $year->id)
            ->set('generationMonth', '10')
            ->call('generateMonthlyBills')
            ->assertHasNoErrors()
            ->assertSee('1 tagihan berhasil dibuat.');

        $billing = SppBilling::query()->where('branch_id', $branch->id)->sole();
        $auditQuery = AuditLog::query()
            ->where('auditable_type', SppBilling::class)
            ->where('auditable_id', $billing->id);
        $this->assertSame('SPP Audit Finance', $auditQuery->sole()->actor_name);

        $component->call('generateMonthlyBills')
            ->assertHasNoErrors()
            ->assertSee('0 tagihan berhasil dibuat.');
        $this->assertSame(1, $auditQuery->count());
    }

    public function test_class_rename_logs_bulk_student_updates(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Class Audit Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $admin = User::query()->create([
            'name' => 'Class Audit Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);
        $schoolClass = SchoolClass::query()->create([
            'branch_id' => $branch->id,
            'name' => '1A',
        ]);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => Str::random(12),
            'name' => 'Class Audit Student',
            'current_class' => '1A',
        ]);

        Livewire::actingAs($admin)
            ->test(SchoolStructureManager::class)
            ->call('edit', 'class', $schoolClass->id)
            ->set('name', '1B')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1B', $student->fresh()->current_class);
        $studentUpdate = AuditLog::query()
            ->where('auditable_type', Student::class)
            ->where('auditable_id', $student->id)
            ->where('event', 'updated')
            ->sole();
        $this->assertSame('Class Audit Admin', $studentUpdate->actor_name);
        $this->assertSame('1A', $studentUpdate->old_values['current_class']);
        $this->assertSame('1B', $studentUpdate->new_values['current_class']);
    }

    public function test_activity_log_is_restricted_to_central_admins(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Restricted Audit Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Branch Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('activity-logs'))->assertForbidden();
    }

    public function test_central_admin_can_access_audit_logs_and_access_is_logged(): void
    {
        $centralAdmin = User::query()->create([
            'name' => 'Central Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'central-admin-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);

        $this->actingAs($centralAdmin)
            ->get(route('activity-logs'))
            ->assertOk()
            ->assertSee('Log aktivitas');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $centralAdmin->id,
            'event' => 'viewed',
            'auditable_type' => AuditLog::class,
            'auditable_id' => 'activity-logs',
        ]);
    }

}

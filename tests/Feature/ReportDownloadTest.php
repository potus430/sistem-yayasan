<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ZipArchive;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_admin_can_export_only_students_from_their_branch(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Test Branch '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $otherBranch = Branch::query()->create([
            'name' => 'Other Branch '.Str::uuid(),
            'level' => 'SMP',
            'address' => 'Other address',
        ]);
        $user = User::query()->create([
            'name' => 'Report Test User',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => Str::random(12),
            'name' => '=SUM(1,1)',
            'current_class' => 'X-A',
        ]);
        Student::query()->create([
            'branch_id' => $otherBranch->id,
            'nisn' => Str::random(12),
            'name' => 'Should not be exported',
            'current_class' => 'IX-A',
        ]);

        $response = $this->actingAs($user)->get(route('reports.download', ['report' => 'students']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('NISN', $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringNotContainsString('Should not be exported', $csv);

        $pdf = $this->actingAs($user)->get(route('reports.download', ['report' => 'students', 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $excel = $this->actingAs($user)->get(route('reports.download', ['report' => 'students', 'format' => 'xlsx']));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'report-xlsx-test-');
        $this->assertNotFalse($xlsxPath);
        file_put_contents($xlsxPath, $excel->streamedContent());
        $workbook = new ZipArchive;
        $this->assertTrue($workbook->open($xlsxPath));
        $sheet = $workbook->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('X-A', $sheet);
        $this->assertStringNotContainsString('Should not be exported', $sheet);
        $workbook->close();
        unlink($xlsxPath);
    }

    public function test_central_admin_employee_export_contains_staff_but_not_teachers(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Staff Export Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Central Report Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);

        foreach (['GURU' => 'Teacher Private Data', 'STAFF' => 'Staff Exported'] as $type => $name) {
            Employee::query()->create([
                'branch_id' => $branch->id,
                'nip' => Str::uuid(),
                'name' => $name,
                'type' => $type,
                'base_salary' => 1000000,
                'status' => 'TETAP',
            ]);
        }

        $response = $this->actingAs($user)->get(route('reports.download', ['report' => 'employees']));
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Staff Exported', $csv);
        $this->assertStringNotContainsString('Teacher Private Data', $csv);
    }

    public function test_central_admin_can_export_branch_summary_as_pdf_and_excel(): void
    {
        $user = User::query()->create([
            'name' => 'Branch Summary Export Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        Branch::query()->create([
            'name' => 'Summary Export Branch',
            'level' => 'SD',
            'address' => 'Test address',
        ]);

        $pdf = $this->actingAs($user)->get(route('reports.download', [
            'report' => 'branch-summary',
            'format' => 'pdf',
        ]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $excel = $this->actingAs($user)->get(route('reports.download', [
            'report' => 'branch-summary',
            'format' => 'xlsx',
        ]));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

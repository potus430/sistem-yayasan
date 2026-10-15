<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\OrganizationSetting;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class PayrollSlipPdfTest extends TestCase
{
    use DatabaseTransactions;

    public function test_finance_user_can_download_multiple_salary_slips_on_one_pdf_page(): void
    {
        Storage::fake('public');
        $logoPath = UploadedFile::fake()
            ->image('foundation-logo.png')
            ->storePubliclyAs('identity', 'foundation-logo.png', 'public');
        OrganizationSetting::current()->update(['logo_path' => $logoPath]);

        $branch = Branch::query()->create([
            'name' => 'PDF Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Finance PDF Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'keuangan',
            'is_active' => true,
        ]);
        $centralFinance = User::query()->create([
            'name' => 'Central Finance PDF Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'keuangan_pusat',
            'is_active' => true,
        ]);

        $employeeIds = [];
        foreach ([
            ['NIP-PDF-TEACHER-'.Str::random(5), 'Guru PDF Test', 'GURU'],
            ['NIP-PDF-STAFF-'.Str::random(5), 'Staff PDF Test', 'STAFF'],
        ] as [$nip, $name, $type]) {
            $employee = Employee::query()->create([
                'branch_id' => $branch->id,
                'nip' => $nip,
                'name' => $name,
                'type' => $type,
                'base_salary' => 600000,
                'status' => 'TETAP',
            ]);
            $employeeIds[] = $employee->id;

            Salary::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'period' => '2026-10',
                'base_salary' => 600000,
                'teaching_hours' => $type === 'GURU' ? 80 : 0,
                'hourly_rate' => $type === 'GURU' ? 7500 : 0,
                'incentives' => 50000,
                'incentive_details' => [['name' => 'Insentif uji', 'amount' => 50000, 'type' => 'MANUAL']],
                'allowances' => 30000,
                'allowance_details' => [['name' => 'Transport', 'type' => 'TRANSPORT', 'amount' => 30000]],
                'deductions' => 10000,
                'net_salary' => 670000,
                'status' => 'PAID',
            ]);
        }

        $response = $this->actingAs($user)->get(route('payroll.slips.pdf', ['period' => '2026-10']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=slip-gaji-2026-10-'.Str::slug($branch->name).'.pdf');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
        $fourPerPage = $this->actingAs($user)->get(route('payroll.slips.pdf', [
            'period' => '2026-10',
            'per_page' => 4,
        ]));
        $fourPerPage->assertOk();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $fourPerPage->getContent()));
        $onePerPage = $this->actingAs($user)->get(route('payroll.slips.pdf', [
            'period' => '2026-10',
            'per_page' => 1,
        ]));
        $onePerPage->assertOk();
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $onePerPage->getContent()));

        $individualResponse = $this->actingAs($user)->get(route('payroll.slips.pdf', [
            'period' => '2026-10',
            'employee_id' => $employeeIds[0],
        ]));
        $individualResponse->assertOk();
        $individualResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline;', $individualResponse->headers->get('content-disposition'));
        $individualPdf = $individualResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $individualPdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $individualPdf));

        $this->actingAs($user)
            ->get(route('payroll.export.xlsx', ['period' => '2026-10']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $excelResponse = $this->actingAs($centralFinance)->get(route('payroll.export.xlsx', [
            'period' => '2026-10',
            'branch_id' => $branch->id,
        ]));
        $excelResponse->assertOk();
        $excelResponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $excelResponse->headers->get('content-disposition'));
        $xlsxPath = tempnam(sys_get_temp_dir(), 'payroll-xlsx-test-');
        $this->assertNotFalse($xlsxPath);
        file_put_contents($xlsxPath, $excelResponse->streamedContent());

        $workbook = new ZipArchive;
        $this->assertTrue($workbook->open($xlsxPath));
        $sheetXml = $workbook->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheetXml);
        $this->assertNotFalse(simplexml_load_string($sheetXml));
        $this->assertStringContainsString('Guru PDF Test', $sheetXml);
        $this->assertStringContainsString('Staff PDF Test', $sheetXml);
        $workbook->close();
        unlink($xlsxPath);
    }

    public function test_users_without_payroll_access_cannot_download_salary_slips(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Unauthorized PDF Test '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Branch Admin PDF Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('payroll.slips.pdf', ['period' => '2026-10']))
            ->assertForbidden();
    }
}

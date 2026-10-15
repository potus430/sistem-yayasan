<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
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
    }
}

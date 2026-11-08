<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use Database\Seeders\SampleStudentProfileSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SampleStudentProfileSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_fills_missing_fields_only_for_seeded_demo_students(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Demo Profile '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Example address',
        ]);
        $demoStudent = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'D01000001',
            'name' => 'Demo Student',
            'current_class' => '1A',
            'address' => 'Existing demo address',
        ]);
        $regularStudent = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'REAL-001',
            'name' => 'Regular Student',
            'current_class' => '1A',
        ]);

        $this->seed(SampleStudentProfileSeeder::class);

        $demoStudent->refresh();
        $this->assertSame('Existing demo address', $demoStudent->address);
        $this->assertSame('Wali dari Demo Student', $demoStudent->guardian_name);
        $this->assertSame('TK Contoh', $demoStudent->previous_school);
        $this->assertNotNull($demoStudent->birth_date);
        $this->assertNull($regularStudent->fresh()->guardian_name);
    }
}

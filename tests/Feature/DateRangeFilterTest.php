<?php

namespace Tests\Feature;

use App\Livewire\StudentDirectory;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DateRangeFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_student_table_filters_by_date_month_year_and_custom_range(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Date Filter Branch '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Date Filter Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        $students = collect([
            ['name' => 'April Student', 'created_at' => '2026-04-10'],
            ['name' => 'May Student', 'created_at' => '2026-05-10'],
            ['name' => 'Next Year Student', 'created_at' => '2027-05-10'],
        ])->map(function (array $data) use ($branch): Student {
            $student = Student::query()->create([
                'branch_id' => $branch->id,
                'nisn' => Str::random(12),
                'name' => $data['name'],
                'current_class' => 'X-A',
            ]);
            $student->forceFill(['created_at' => Carbon::parse($data['created_at'])])->saveQuietly();

            return $student;
        });

        Livewire::actingAs($user)
            ->test(StudentDirectory::class)
            ->set('dateFilterMode', 'date')
            ->set('dateFilterDate', '2026-04-10')
            ->assertSee('April Student')
            ->assertDontSee('May Student')
            ->set('dateFilterMode', 'month')
            ->set('dateFilterMonth', '2026-05')
            ->assertSee('May Student')
            ->assertDontSee('April Student')
            ->assertDontSee('Next Year Student')
            ->set('dateFilterMode', 'year')
            ->set('dateFilterYear', '2027')
            ->assertSee('Next Year Student')
            ->assertDontSee('April Student')
            ->set('dateFilterMode', 'range')
            ->set('dateFilterFrom', '2026-04-01')
            ->set('dateFilterTo', '2026-05-31')
            ->assertSee('April Student')
            ->assertSee('May Student')
            ->assertDontSee('Next Year Student');

        $export = $this->actingAs($user)->get(route('reports.download', [
            'report' => 'students',
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('April Student', $csv);
        $this->assertStringNotContainsString('May Student', $csv);

        $this->assertCount(3, $students);
    }
}

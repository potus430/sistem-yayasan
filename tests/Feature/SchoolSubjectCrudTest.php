<?php

namespace Tests\Feature;

use App\Livewire\SchoolStructureManager;
use App\Models\Branch;
use App\Models\SchoolSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolSubjectCrudTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_admin_can_create_update_and_delete_a_school_subject(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Subject CRUD '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Subject CRUD Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(SchoolStructureManager::class)
            ->call('create', 'subject')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $subject = SchoolSubject::query()
            ->where('branch_id', $branch->id)
            ->where('name', 'Matematika')
            ->firstOrFail();

        $component->call('edit', 'subject', $subject->id)
            ->set('name', 'Aljabar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_subjects', [
            'id' => $subject->id,
            'branch_id' => $branch->id,
            'name' => 'Aljabar',
        ]);
        $this->assertDatabaseMissing('school_subjects', [
            'id' => $subject->id,
            'name' => 'Matematika',
        ]);

        $component->call('delete', 'subject', $subject->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('school_subjects', ['id' => $subject->id]);
    }
}

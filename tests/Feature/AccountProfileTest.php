<?php

namespace Tests\Feature;

use App\Livewire\AccountProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_update_own_profile_and_password_without_changing_access_scope(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Profile Test '.Str::uuid(),
            'level' => 'SMA',
            'address' => 'Test address',
        ]);
        $user = User::query()->create([
            'name' => 'Profile Test User',
            'email' => Str::uuid().'@example.test',
            'password' => 'old-password',
            'branch_id' => $branch->id,
            'role' => 'guru',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(AccountProfile::class)
            ->set('name', 'Updated Profile Name')
            ->set('email', 'updated.'.Str::uuid().'@example.test')
            ->set('currentPassword', 'old-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Profil akun berhasil diperbarui.');

        $user->refresh();
        $this->assertSame('Updated Profile Name', $user->name);
        $this->assertSame('guru', $user->role);
        $this->assertSame($branch->id, $user->branch_id);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_user_must_confirm_current_password_before_saving_profile_changes(): void
    {
        $user = User::query()->create([
            'name' => 'Profile Password Test',
            'email' => Str::uuid().'@example.test',
            'password' => 'valid-old-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(AccountProfile::class)
            ->set('name', 'Name Must Not Save')
            ->set('currentPassword', 'incorrect-password')
            ->call('save')
            ->assertHasErrors('currentPassword');

        $this->assertSame('Profile Password Test', $user->fresh()->name);
    }
}

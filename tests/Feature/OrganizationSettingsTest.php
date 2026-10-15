<?php

namespace Tests\Feature;

use App\Livewire\OrganizationSettingsManager;
use App\Models\Branch;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_admin_can_update_foundation_identity_and_branch_user_sees_branch_name_as_app_title(): void
    {
        $centralAdmin = User::query()->create([
            'name' => 'Branding Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'name' => 'SD Contoh Branding',
            'level' => 'SD',
            'address' => 'Jl. Pendidikan No. 10',
        ]);
        $branchUser = User::query()->create([
            'name' => 'Branch Branding User',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        Livewire::actingAs($centralAdmin)
            ->test(OrganizationSettingsManager::class)
            ->set('applicationName', 'Sistem Yayasan Modern')
            ->set('foundationName', 'Yayasan Pendidikan Cendekia')
            ->set('foundationAddress', 'Jl. Merdeka No. 25, Kota Pendidikan')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Identitas aplikasi dan yayasan berhasil diperbarui.');

        $settings = OrganizationSetting::current();
        $this->assertSame('Sistem Yayasan Modern', $settings->application_name);
        $this->assertSame('Yayasan Pendidikan Cendekia', $settings->foundation_name);
        $this->assertSame('Jl. Merdeka No. 25, Kota Pendidikan', $settings->foundation_address);

        $this->actingAs($branchUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>Dashboard · SD Contoh Branding · Yayasan Pendidikan Cendekia</title>', false)
            ->assertSee('SD Contoh Branding · Yayasan Pendidikan Cendekia');
    }

    public function test_non_central_user_cannot_manage_organization_identity(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Restricted Branch '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
        $branchUser = User::query()->create([
            'name' => 'Restricted Branding User',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch->id,
            'role' => 'admin_cabang',
            'is_active' => true,
        ]);

        $this->actingAs($branchUser)
            ->get(route('organization-settings'))
            ->assertForbidden();
    }

    public function test_central_admin_can_upload_replace_and_remove_foundation_logo(): void
    {
        Storage::fake('public');
        $admin = User::query()->create([
            'name' => 'Logo Admin',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => null,
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationSettingsManager::class)
            ->set('logo', TemporaryUploadedFile::fake()->image('foundation-logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $firstLogoPath = OrganizationSetting::current()->logo_path;
        $this->assertNotNull($firstLogoPath);
        Storage::disk('public')->assertExists($firstLogoPath);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('alt="Logo Yayasan"', false);
        auth()->logout();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('alt="Logo Yayasan"', false);
        $this->actingAs($admin);

        $component->set('logo', TemporaryUploadedFile::fake()->image('replacement-logo.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $replacementPath = OrganizationSetting::current()->logo_path;
        $this->assertNotSame($firstLogoPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstLogoPath);
        Storage::disk('public')->assertExists($replacementPath);

        $component->call('removeLogo')
            ->assertHasNoErrors();

        $this->assertNull(OrganizationSetting::current()->logo_path);
        Storage::disk('public')->assertMissing($replacementPath);
    }
}

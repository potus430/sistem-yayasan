<?php

namespace App\Livewire;

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrganizationSettingsManager extends Component
{
    use WithFileUploads;

    public string $applicationName = '';

    public string $foundationName = '';

    public string $foundationAddress = '';

    public string $loginPanelBadge = '';

    public string $loginPanelHeadline = '';

    public string $loginPanelDescription = '';

    public $logo = null;

    public $loginPanelImage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $settings = OrganizationSetting::current();
        $this->applicationName = $settings->application_name;
        $this->foundationName = $settings->foundation_name;
        $this->foundationAddress = (string) $settings->foundation_address;
        $this->loginPanelBadge = (string) $settings->login_panel_badge;
        $this->loginPanelHeadline = (string) $settings->login_panel_headline;
        $this->loginPanelDescription = (string) $settings->login_panel_description;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $validated = $this->validate([
            'applicationName' => ['required', 'string', 'max:120'],
            'foundationName' => ['required', 'string', 'max:180'],
            'foundationAddress' => ['nullable', 'string', 'max:2000'],
            'loginPanelBadge' => ['required', 'string', 'max:100'],
            'loginPanelHeadline' => ['required', 'string', 'max:180'],
            'loginPanelDescription' => ['required', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'loginPanelImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $settings = OrganizationSetting::current();
        $previousLogoPath = $settings->logo_path;
        $newLogoPath = null;
        if ($validated['logo'] !== null) {
            $extension = $validated['logo']->guessExtension();
            abort_unless($extension, 422, 'Format gambar logo tidak dapat ditentukan.');
            $newLogoPath = $validated['logo']->storePubliclyAs(
                'identity',
                Str::uuid().'.'.$extension,
                'public'
            );
            if (! $newLogoPath) {
                throw new \RuntimeException('Logo yayasan gagal disimpan ke penyimpanan publik.');
            }
        }
        $previousPanelImagePath = $settings->login_panel_image_path;
        $newPanelImagePath = null;
        if ($validated['loginPanelImage'] !== null) {
            $extension = $validated['loginPanelImage']->guessExtension();
            abort_unless($extension, 422, 'Format gambar halaman login tidak dapat ditentukan.');
            $newPanelImagePath = $validated['loginPanelImage']->storePubliclyAs(
                'identity/login',
                Str::uuid().'.'.$extension,
                'public'
            );
            if (! $newPanelImagePath) {
                throw new \RuntimeException('Gambar panel login gagal disimpan.');
            }
        }

        $settings->update([
            'application_name' => trim($validated['applicationName']),
            'foundation_name' => trim($validated['foundationName']),
            'foundation_address' => $validated['foundationAddress'] !== null
                ? trim($validated['foundationAddress'])
                : null,
            'logo_path' => $newLogoPath ?? $previousLogoPath,
            'login_panel_badge' => trim($validated['loginPanelBadge']),
            'login_panel_headline' => trim($validated['loginPanelHeadline']),
            'login_panel_description' => trim($validated['loginPanelDescription']),
            'login_panel_image_path' => $newPanelImagePath ?? $previousPanelImagePath,
        ]);

        if ($newLogoPath !== null && $previousLogoPath !== null && ! Storage::disk('public')->delete($previousLogoPath)) {
            throw new \RuntimeException('Logo lama gagal dihapus dari penyimpanan publik.');
        }
        if ($newPanelImagePath !== null && $previousPanelImagePath !== null
            && ! Storage::disk('public')->delete($previousPanelImagePath)) {
            throw new \RuntimeException('Gambar panel login lama gagal dihapus.');
        }

        $this->reset(['logo', 'loginPanelImage']);
        session()->flash('success', 'Identitas aplikasi, yayasan, dan panel login berhasil diperbarui.');
    }

    public function removeLogo(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $settings = OrganizationSetting::current();
        $logoPath = $settings->logo_path;
        $settings->update(['logo_path' => null]);
        if ($logoPath !== null && ! Storage::disk('public')->delete($logoPath)) {
            throw new \RuntimeException('Logo yayasan gagal dihapus dari penyimpanan publik.');
        }

        $this->reset('logo');
        session()->flash('success', 'Logo yayasan berhasil dihapus.');
    }

    public function removeLoginPanelImage(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $settings = OrganizationSetting::current();
        $imagePath = $settings->login_panel_image_path;
        $settings->update(['login_panel_image_path' => null]);
        if ($imagePath !== null && ! Storage::disk('public')->delete($imagePath)) {
            throw new \RuntimeException('Gambar panel login gagal dihapus.');
        }

        $this->reset('loginPanelImage');
        session()->flash('success', 'Gambar panel login berhasil dihapus.');
    }

    public function render()
    {
        return view('livewire.organization-settings-manager', [
            'settings' => OrganizationSetting::current(),
        ])
            ->layout('layouts.app', ['title' => 'Identitas Aplikasi']);
    }
}

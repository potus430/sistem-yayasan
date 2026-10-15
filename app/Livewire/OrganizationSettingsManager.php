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

    public $logo = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $settings = OrganizationSetting::current();
        $this->applicationName = $settings->application_name;
        $this->foundationName = $settings->foundation_name;
        $this->foundationAddress = (string) $settings->foundation_address;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);

        $validated = $this->validate([
            'applicationName' => ['required', 'string', 'max:120'],
            'foundationName' => ['required', 'string', 'max:180'],
            'foundationAddress' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        $settings->update([
            'application_name' => trim($validated['applicationName']),
            'foundation_name' => trim($validated['foundationName']),
            'foundation_address' => $validated['foundationAddress'] !== null
                ? trim($validated['foundationAddress'])
                : null,
            'logo_path' => $newLogoPath ?? $previousLogoPath,
        ]);

        if ($newLogoPath !== null && $previousLogoPath !== null && ! Storage::disk('public')->delete($previousLogoPath)) {
            throw new \RuntimeException('Logo lama gagal dihapus dari penyimpanan publik.');
        }

        $this->reset('logo');
        session()->flash('success', 'Identitas aplikasi dan yayasan berhasil diperbarui.');
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

    public function render()
    {
        return view('livewire.organization-settings-manager', [
            'settings' => OrganizationSetting::current(),
        ])
            ->layout('layouts.app', ['title' => 'Identitas Aplikasi']);
    }
}

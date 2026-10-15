<?php

namespace App\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;

class AccountProfile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function save(): void
    {
        $user = auth()->user();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'currentPassword' => ['required', 'current_password:web'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if ($validated['password'] !== null && $validated['password'] !== '') {
            $user->password = $validated['password'];
        }
        $user->save();

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $this->reset(['currentPassword', 'password', 'password_confirmation']);
        $this->dispatch('profile-saved');
        session()->flash('success', 'Profil akun berhasil diperbarui.');
    }

    public function render()
    {
        return view('livewire.account-profile')
            ->layout('layouts.app', ['title' => 'Profil Akun']);
    }
}

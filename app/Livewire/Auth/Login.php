<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $credentials = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ]);
        $rateLimitKey = Str::transliterate(Str::lower($credentials['email']).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('email', 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($rateLimitKey).' detik.');

            return null;
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $this->remember)) {
            RateLimiter::hit($rateLimitKey, 60);
            $this->addError('email', 'Email atau kata sandi tidak valid.');

            return null;
        }

        RateLimiter::clear($rateLimitKey);
        request()->session()->regenerate();

        return $this->redirectRoute(Auth::user()->role === 'staff_pusat' ? 'profile' : 'dashboard', navigate: true);
    }

    public function render()
    {
        return view('auth.login')->layout('layouts.guest');
    }
}

<?php

namespace App\Livewire\Cms;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;

class Pagelogin extends Component
{
    public string $email = '';
    public string $password = '';

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required|string',
    ];

    public function mount()
    {
        if (session()->has('account_id')) {
            return redirect('/' . app()->getLocale() . '/cms');
        }
    }

    public function login()
    {
        $this->validate();

        // Batas percobaan gagal: 5 kali per menit untuk satu email dari satu IP, dan 20 kali per menit
        // untuk satu IP (menebak banyak email sekaligus)
        $throttleKey = Str::lower($this->email) . '|' . request()->ip();
        $throttleIp = 'login-ip|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($throttleIp, 20)) {
            $seconds = max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($throttleIp));
            $this->addError('email', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");
            return;
        }

        $user = DB::table('accounts')
            ->where('email', $this->email)
            ->first();

        // Pesan yang sama untuk email tidak terdaftar dan password salah, supaya daftar email
        // akun tidak bisa ditebak dari pesan login
        if (!$user || !Hash::check($this->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($throttleIp, 60);
            $this->addError('email', 'Email atau password salah.');
            return;
        }

        // Akun berstatus Nonaktif (N) di menu Account tidak bisa masuk
        if ($user->status !== 'Y') {
            $this->addError('email', 'Akun ini dinonaktifkan. Hubungi admin.');
            return;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        Session::put('account_id', $user->id);
        Session::put('account_role', $user->role);

        return redirect('/' . app()->getLocale() . '/cms');
    }

    public function render()
    {
        return view('livewire.cms.pagelogin');
    }
}
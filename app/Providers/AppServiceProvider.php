<?php

namespace App\Providers;

use App\Http\Middleware\cmslogin;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Middleware rute halaman tidak otomatis berlaku untuk aksi Livewire (simpan, hapus, dst.);
        // tanpa ini, tab CMS yang masih terbuka tetap bisa mengubah data setelah logout/sesi habis
        Livewire::addPersistentMiddleware([cmslogin::class]);
    }
}

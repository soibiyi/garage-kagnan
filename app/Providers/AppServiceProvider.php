<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        Vite::prefetch(concurrency: 3);

        // Historique des connexions des collaborateurs (page Sécurité du super administrateur)
        Event::listen(Login::class, [\App\Support\Securite::class, 'surConnexion']);
        Event::listen(Failed::class, [\App\Support\Securite::class, 'surEchec']);
    }
}

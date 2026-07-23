<?php

namespace App\Providers;

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
        // LogAgentToolCalls is auto-discovered from app/Listeners; no explicit
        // Event::listen needed (a second registration would log each tool twice).
    }
}

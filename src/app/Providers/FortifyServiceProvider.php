<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortifyが自動でルートを登録するのを止める（方針A）
        \Laravel\Fortify\Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        //Fortifyの機能は使わない
    }
}

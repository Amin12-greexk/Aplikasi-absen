<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Absensi;
use App\Observers\AbsensiObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
      public function register(): void
    {
        // Register FingerspotService as singleton
        $this->app->singleton(FingerspotService::class, function ($app) {
            return new FingerspotService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Absensi::observe(AbsensiObserver::class);
    }
}

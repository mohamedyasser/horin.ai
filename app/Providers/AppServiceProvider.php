<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Observers\StaticDataObserver;
use Illuminate\Support\Facades\URL;
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
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Register observer for static data cache invalidation
        Country::observe(StaticDataObserver::class);
        Market::observe(StaticDataObserver::class);
        Sector::observe(StaticDataObserver::class);
        Asset::observe(StaticDataObserver::class);
    }
}

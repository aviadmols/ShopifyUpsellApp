<?php

namespace App\Providers;

use App\Models\ThankYouBlock;
use Illuminate\Support\Facades\Route;
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
        Route::bind('block', fn ($value) => ThankYouBlock::findOrFail($value));

        // Behind Railway/proxy: force HTTPS so assets and links use https (avoid Mixed Content)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

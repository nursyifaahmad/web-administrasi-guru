<?php

namespace App\Providers;

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
        $appUrl = (string) config('app.url');
        $configuredHttpsHost = str_starts_with($appUrl, 'https://')
            ? parse_url($appUrl, PHP_URL_HOST)
            : null;

        $isHttpsProxy = request()->header('X-Forwarded-Proto') === 'https'
            || str_contains((string) request()->header('CF-Visitor'), '"https"')
            || ($configuredHttpsHost && request()->getHost() === $configuredHttpsHost);

        if ($isHttpsProxy) {
            URL::forceScheme('https');
        }
    }
}

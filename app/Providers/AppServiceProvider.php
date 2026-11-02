<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Gunakan path relatif (/build/...) agar aset CSS/JS selalu mengikuti domain & port aktif
        // (baik via IP Tailscale :4010, LAN lokal :4010, maupun HTTPS Cloudflare Tunnel).
        Vite::createAssetPathsUsing(fn (string $path) => '/'.ltrim($path, '/'));

        $isHttpsProxy = request()->header('X-Forwarded-Proto') === 'https'
            || str_contains((string) request()->header('CF-Visitor'), '"https"');

        if ($isHttpsProxy) {
            URL::forceScheme('https');
        }
    }
}

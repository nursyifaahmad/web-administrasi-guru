<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ShareWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'workspace' => ShareWorkspaceContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->is('logout') || $request->routeIs('logout')) {
                return redirect()->route('login')->with('status', 'Anda telah keluar dari sistem.');
            }

            return redirect()->guest(route('login'))
                ->with('warning', 'Sesi Anda telah kedaluwarsa karena tidak ada aktivitas. Silakan login kembali.');
        });
    })->create();

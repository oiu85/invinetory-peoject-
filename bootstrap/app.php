<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Disable CSRF for all API routes (we use token-based auth, not cookies)
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Add CORS middleware for API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'driver' => \App\Http\Middleware\EnsureUserIsDriver::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\InternalApiKeyMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Daftarkan middleware aliases
        $middleware->alias([
            'jwt.auth'     => JwtAuthMiddleware::class,
            'internal.key' => InternalApiKeyMiddleware::class,
        ]);

        // Global API middleware
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua exception dirender via App\Exceptions\Handler
    })
    ->create();

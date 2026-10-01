<?php

use App\Http\Middleware\EnsurePatientDataConsent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register POPIA consent middleware
        $middleware->alias([
            'popia.consent' => EnsurePatientDataConsent::class,
        ]);

        // Apply to API routes for patient data protection
        $middleware->api(prepend: [
            EnsurePatientDataConsent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

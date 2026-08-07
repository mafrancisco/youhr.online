<?php

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
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenantContext::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\ResolveTenantContext::class,
        ]);
        // Run tenant resolution immediately after the session starts, which places it
        // before Authenticate and SubstituteBindings while preserving Laravel's
        // default cookie/session middleware ordering.
        $middleware->appendToPriorityList(
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\ResolveTenantContext::class,
        );
        $middleware->alias([
            'hr'       => \App\Http\Middleware\EnsureIsHR::class,
            'employee' => \App\Http\Middleware\EnsureIsEmployee::class,
            'tenant'   => \App\Http\Middleware\RequireTenantContext::class,
            'licensed' => \App\Http\Middleware\EnsureTenantLicensed::class,
            'landlord' => \App\Http\Middleware\EnsureIsLandlordAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

<?php

use App\Http\Middleware\EnsureMainDomain;
use App\Http\Middleware\EnsureTenantDomain;
use App\Http\Middleware\InjectTenantTheme;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Honeypot\ProtectAgainstSpam;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ResolveTenant::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', InjectTenantTheme::class);

        // Apply generous rate limiting (120/min) to all web requests (BR-153).
        // Specific routes override with stricter tiers (strict, moderate).
        $middleware->appendToGroup('web', \Illuminate\Routing\Middleware\ThrottleRequests::class.':generous');

        $middleware->alias([
            'main.domain' => EnsureMainDomain::class,
            'tenant.domain' => EnsureTenantDomain::class,
            'honeypot' => ProtectAgainstSpam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

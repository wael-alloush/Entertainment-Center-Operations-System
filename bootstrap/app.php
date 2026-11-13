<?php

use App\Http\Middleware\AssignDeviceCookie;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureBonusExpiryChecked;
use App\Http\Middleware\EnsureTrustedDevice;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'active' => EnsureUserIsActive::class,
            'trusted' => EnsureTrustedDevice::class,
            'bonus-expiry' => EnsureBonusExpiryChecked::class,
        ]);

        $middleware->web(append: [
            AssignDeviceCookie::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

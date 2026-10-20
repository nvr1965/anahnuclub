<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRulesAccepted;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TouchLastSeen;
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
        // Shared hosting (SiteGround) serves through a proxy; without this every URL comes out as http://.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['admin' => EnsureAdmin::class, 'rules' => EnsureRulesAccepted::class]);
        $middleware->web(append: [
            SetLocale::class,
            TouchLastSeen::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

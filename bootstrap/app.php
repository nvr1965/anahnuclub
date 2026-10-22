<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRulesAccepted;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreviewForGuests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TouchLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Shared hosting (SiteGround) serves through a proxy; without this every URL comes out as http://.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['admin' => EnsureAdmin::class, 'rules' => EnsureRulesAccepted::class, 'preview.guest' => PreviewForGuests::class]);
        $middleware->web(append: [
            SetLocale::class,
            TouchLastSeen::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An old or cut "confirm your email" link: instead of a bare 403, the page that sends a new one.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('verification.verify') && $request->user()) {
                return $request->user()->hasVerifiedEmail()
                    ? redirect()->route('dashboard')
                    : redirect()->route('verification.notice')->with('status', 'verification-link-expired');
            }
        });
        // A page left open past its session (CSRF token 419): reload it with a fresh token instead of an error box.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419) {
                return back()->withInput($request->except('password', 'password_confirmation'));
            }

            return $response;
        });
    })->create();

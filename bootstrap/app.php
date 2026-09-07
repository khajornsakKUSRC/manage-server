<?php

use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsurePagePermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleUrlPrefix;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Sub-path hosting (APP_URL_PREFIX): must run before anything reads
        // the request path, hence a global prepend. It also injects the
        // X-Forwarded-Prefix header locally; trusting that header (already
        // in the default trusted set) is what makes url()/route()/asset()
        // and Inertia's page.url come back with the prefix.
        //
        // Trust localhost + the private ranges a front proxy normally sits
        // on. Add the real proxy IP here if it's outside these. env() is
        // deliberately not used — this closure runs before .env is loaded
        // on the HTTP path.
        $middleware->prepend(HandleUrlPrefix::class);
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
        ]);

        // Must run before Laravel's own StartSession (part of the default
        // `web` group) reads session.lifetime, so prepended rather than
        // appended — see ApplySystemSettings.
        $middleware->web(prepend: [
            ApplySystemSettings::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackUserActivity::class,
        ]);

        $middleware->alias([
            'page' => EnsurePagePermission::class,
            'admin' => EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

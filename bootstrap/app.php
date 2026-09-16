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
        // In production (services.src.ku.ac.th) nginx terminates the public
        // connection directly — there is no private-network load balancer
        // in front of it, so a real visitor's REMOTE_ADDR is never in a
        // trusted-proxy CIDR range, and the X-Forwarded-Prefix header above
        // (and X-Forwarded-Host/Proto/Port/For) never got trusted. That
        // silently dropped the /manage-server prefix from every generated
        // URL — redirects, route()/asset(), Inertia's page.url — for every
        // real visitor.
        //
        // '*' makes nginx itself (not a distinct private-IP proxy) the
        // trust boundary, which only holds because the nginx location
        // block for this site (ku_project.test.conf) overwrites any
        // client-supplied X-Forwarded-Prefix/Host/Proto/Port/For before
        // passing the request to PHP-FPM — otherwise a visitor could spoof
        // those headers directly. Don't set this to '*' without that nginx
        // side in place.
        $middleware->prepend(HandleUrlPrefix::class);
        $middleware->trustProxies(at: '*');

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

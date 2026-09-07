<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the whole app run under a sub-path (config app.url_prefix), e.g.
 * https://services.src.ku.ac.th/manage-server, without rewriting every
 * route.
 *
 * Registered with $middleware->prepend() so it runs before anything reads
 * the request path. It does two things:
 *
 *  1. Announces the public prefix via X-Forwarded-Prefix so url(), route(),
 *     asset(), redirects and Inertia's `page.url` all come back with the
 *     prefix in front — Symfony's Request::getBaseUrl() honours that header
 *     for trusted proxies (see trustProxies() in bootstrap/app.php).
 *
 *  2. Strips a leading "/<prefix>" from REQUEST_URI if present, so route
 *     matching works whether the front proxy forwards the prefix or strips
 *     it — and so it works locally with no proxy at all.
 */
class HandleUrlPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = trim((string) config('app.url_prefix'), '/');

        if ($prefix === '') {
            return $next($request);
        }

        $prefix = '/'.$prefix;

        // 1. Make generated URLs prefix-aware. A real proxy's own
        //    X-Forwarded-Prefix, if any, wins.
        if (! $request->headers->has('X-Forwarded-Prefix')) {
            $request->headers->set('X-Forwarded-Prefix', $prefix);
            $request->server->set('HTTP_X_FORWARDED_PREFIX', $prefix);
        }

        // 2. Route matching sees the path without the prefix. Safe to
        //    mutate the raw REQUEST_URI here because Symfony computes
        //    (and caches) requestUri/pathInfo/baseUrl lazily, and nothing
        //    in the stack has touched them yet at prepend() position.
        $uri = (string) $request->server->get('REQUEST_URI', '');

        if ($uri === $prefix || str_starts_with($uri, $prefix.'/') || str_starts_with($uri, $prefix.'?')) {
            $rest = substr($uri, strlen($prefix));
            $request->server->set('REQUEST_URI', $rest === '' || $rest[0] === '?' ? '/'.$rest : $rest);
        }

        return $next($request);
    }
}

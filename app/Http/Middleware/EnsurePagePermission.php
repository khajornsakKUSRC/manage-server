<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePagePermission
{
    /**
     * Allows the request through if the user can access any one of the
     * given pages, e.g. `page:hosts,dashboard` for a resource shared by
     * both pages. See App\Support\Permissions::PAGES for valid keys.
     */
    public function handle(Request $request, Closure $next, string ...$pages): Response
    {
        $user = $request->user();

        abort_unless(
            $user && collect($pages)->contains(fn (string $page) => $user->canAccess($page)),
            403,
        );

        return $next($request);
    }
}

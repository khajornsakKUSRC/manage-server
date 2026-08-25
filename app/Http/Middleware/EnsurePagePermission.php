<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePagePermission
{
    /**
     * Allows the request through if the user can access any one of the
     * given pages, e.g. `page:hosts,dashboard` for a resource shared by
     * both pages, AND that page hasn't been globally disabled from
     * Settings → Menus (see SystemSetting::disabled_pages) — a page
     * switched off there is unavailable to everyone, including admins,
     * until it's switched back on. See App\Support\Permissions::PAGES for
     * valid keys.
     */
    public function handle(Request $request, Closure $next, string ...$pages): Response
    {
        $user = $request->user();
        $disabled = collect(SystemSetting::current()->disabled_pages ?? []);

        abort_unless(
            $user && collect($pages)->contains(
                fn (string $page) => $user->canAccess($page) && ! $disabled->contains($page)
            ),
            403,
        );

        return $next($request);
    }
}

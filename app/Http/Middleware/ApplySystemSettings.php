<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    /**
     * Applies the admin-configured timezone and session timeout before the
     * rest of the request runs — must run before Laravel's own StartSession
     * middleware (which reads session.lifetime to build the session) and
     * before any date/Carbon formatting happens, hence prepended to the
     * `web` group in bootstrap/app.php rather than appended.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = SystemSetting::current();

        date_default_timezone_set($settings->timezone);
        Config::set('app.timezone', $settings->timezone);
        Config::set('session.lifetime', $settings->session_timeout_minutes);

        return $next($request);
    }
}

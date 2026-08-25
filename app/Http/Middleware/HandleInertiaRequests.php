<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = SystemSetting::current();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Shared on every page — including the (unauthenticated) login
            // page, so the maintenance notice and footer can show there too.
            'siteSettings' => [
                'maintenance_enabled' => $settings->maintenance_mode_enabled,
                'maintenance_message' => $settings->maintenance_message,
                'footer_text' => $settings->footer_text,
                'favicon_url' => $settings->favicon_path
                    ? Storage::disk('public')->url($settings->favicon_path)
                    : null,
                'disabled_pages' => $settings->disabled_pages ?? [],
            ],
        ];
    }
}

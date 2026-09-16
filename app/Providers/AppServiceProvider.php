<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ThemePalette;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->logLoginActivity();
        $this->shareBranding();
    }

    /**
     * Injects the admin-uploaded favicon and chosen colour theme (Settings
     * → Branding / Appearance) into the root Blade view. Done server-side
     * here — rather than via the client-side Inertia "siteSettings" prop —
     * so the very first HTML response already has the right icon and
     * colours, with no flash of the default favicon/theme.
     */
    protected function shareBranding(): void
    {
        View::composer('app', function ($view): void {
            $settings = SystemSetting::current();

            $view->with('faviconUrl', $settings->favicon_path ? Storage::disk('public')->url($settings->favicon_path) : null);
            $view->with('themeStyleTag', ThemePalette::styleTag($settings->theme_color));
            $view->with('themeBackground', ThemePalette::backgroundColors($settings->theme_color));
        });
    }

    /**
     * Records an Activity Log entry (with the login IP) on every successful
     * login, for the Activity Log page.
     */
    protected function logLoginActivity(): void
    {
        Event::listen(function (Login $event): void {
            /** @var User $user */
            $user = $event->user;

            app(ActivityLogger::class)->record(
                action: 'login',
                description: "{$user->name} logged in",
                subjectType: 'auth',
                subjectLabel: $user->email,
                userId: $user->id,
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

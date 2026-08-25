<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
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
        $this->shareFavicon();
    }

    /**
     * Injects the admin-uploaded favicon (Settings → Branding) into the
     * root Blade view's <link rel="icon"> tag. Done server-side here —
     * rather than via the client-side Inertia "siteSettings" prop — so the
     * very first HTML response already has the right icon, with no flash.
     */
    protected function shareFavicon(): void
    {
        View::composer('app', function ($view): void {
            $path = SystemSetting::current()->favicon_path;

            $view->with('faviconUrl', $path ? Storage::disk('public')->url($path) : null);
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

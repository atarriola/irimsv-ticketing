<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureRateLimiting();
        $this->configureUrlScheme();
    }

    /**
     * Configure safe defaults for the database, models and passwords.
     */
    private function configureDefaults(): void
    {
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));

        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn (): ?Password => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()
            : null);
    }

    /**
     * Generate HTTPS links whenever the public APP_URL is HTTPS, even when a
     * TLS-terminating proxy or web server hands PHP a plain HTTP request.
     */
    private function configureUrlScheme(): void
    {
        URL::forceHttps(str_starts_with((string) config('app.url'), 'https://'));
    }

    /**
     * Configure the rate limiters used by the routes.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('posting', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}

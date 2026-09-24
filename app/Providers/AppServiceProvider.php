<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
    }

    /**
     * Configure safe defaults for the database, models and passwords.
     */
    private function configureDefaults(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn (): ?Password => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()
            : null);
    }

    /**
     * Configure the rate limiters used by the routes.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('posting', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}

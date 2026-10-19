<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->configureRateLimiting();
    }

    /**
     * Limits for the authentication routes.
     *
     * Limits per email stop guessing against one account. Limits per IP are
     * kept generous because many Sri Lankan mobile users share one IP
     * address through their carrier.
     */
    private function configureRateLimiting(): void
    {
        $email = fn (Request $request): string => Str::lower((string) $request->input('email'));

        RateLimiter::for('auth-register', fn (Request $request) => [
            Limit::perHour(5)->by('register-email:'.$email($request)),
            Limit::perHour(30)->by('register-ip:'.$request->ip()),
        ]);

        RateLimiter::for('auth-verify', fn (Request $request) => [
            Limit::perMinutes(10, 10)->by('verify-email:'.$email($request)),
            Limit::perMinutes(10, 60)->by('verify-ip:'.$request->ip()),
        ]);

        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.$email($request).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('auth-refresh', fn (Request $request) => [
            Limit::perMinute(60)->by('refresh-ip:'.$request->ip()),
        ]);
    }
}

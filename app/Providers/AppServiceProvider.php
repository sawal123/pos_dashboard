<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
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
     * Throttle member invitation and membership mutations, keyed per actor
     * (or per IP for unauthenticated acceptance attempts).
     */
    protected function configureRateLimiting(): void
    {
        $byActor = static function (Request $request): string {
            $user = $request->user();

            return $user !== null
                ? 'user:'.$user->getAuthIdentifier()
                : 'ip:'.$request->ip();
        };

        foreach ([
            'member-invitations',
            'member-invitation-resend',
            'member-invitation-revoke',
            'member-removal',
            'member-role-update',
            'member-invitation-accept',
        ] as $limiter) {
            RateLimiter::for(
                $limiter,
                static fn (Request $request) => Limit::perMinute(10)->by($byActor($request)),
            );
        }
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

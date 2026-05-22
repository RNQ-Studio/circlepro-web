<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\Push\FcmDriver;
use App\Services\Push\FcmDriverInterface;
use App\Services\Push\LogFcmDriver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use the real FCM driver when Firebase credentials are configured; fall back to log driver.
        $this->app->singleton(FcmDriverInterface::class, function (): FcmDriverInterface {
            $credentials = config('firebase.projects.app.credentials');

            if (filled($credentials)) {
                return new FcmDriver(Firebase::project('app')->messaging());
            }

            return new LogFcmDriver;
        });
    }

    public function boot(): void
    {
        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(now()->addHours(8));
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // spatie's Role model lives in the vendor namespace, so its policy
        // must be registered explicitly (User's policy is auto-discovered).
        Gate::policy(Role::class, RolePolicy::class);

        // super-admin bypasses every authorization check (API + back-office).
        Gate::before(fn (?User $user, string $ability): ?bool => $user?->hasRole('super-admin') ? true : null);

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }
}

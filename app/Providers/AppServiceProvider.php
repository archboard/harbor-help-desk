<?php

namespace App\Providers;

use App\Models\Passkey;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passkeys\Passkeys;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Passkeys::usePasskeyModel(Passkey::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'workspace' => Workspace::class,
            'passkey' => Passkey::class,
        ]);

        Passport::tokensExpireIn(CarbonImmutable::now()->addHour());
        Passport::refreshTokensExpireIn(CarbonImmutable::now()->addDays(30));

        Passport::authorizationView(fn (array $parameters) => Inertia::render('auth/OAuthAuthorize', [
            'client' => ['id' => $parameters['client']->getKey(), 'name' => $parameters['client']->name],
            'scopes' => array_map(fn (Scope $scope): string => $scope->description, $parameters['scopes']),
            'state' => $parameters['request']->state,
            'authToken' => $parameters['authToken'],
            'csrfToken' => csrf_token(),
        ])->toResponse($parameters['request']));

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

<?php

use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'workspace', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance']);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Resolve the workspace before auth and route model binding, so bound tenant models are
        // scoped to it, and check the token's workspace access before anything is looked up.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveWorkspace::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTokenCanAccessWorkspace::class);

        $middleware->group('workspace', [
            ResolveWorkspace::class,
            EnsureValidTenantSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

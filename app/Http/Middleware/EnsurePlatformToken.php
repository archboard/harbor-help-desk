<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformToken
{
    /**
     * Allow the platform (billing, marketing site) through with its bearer token. Self-hosted,
     * or without a token configured, the platform API doesn't exist, so 404.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('harbor.platform_token');

        abort_if(! config('harbor.multitenant') || $token === '', Response::HTTP_NOT_FOUND);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), Response::HTTP_UNAUTHORIZED);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostmarkWebhook
{
    /**
     * Allow Postmark's webhooks, which send the configured secret as the basic auth password
     * (`https://postmark:{secret}@…`). Without a secret the hosted path is off, so 404.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('harbor.mail.postmark_webhook_secret');

        abort_if($secret === '', Response::HTTP_NOT_FOUND);
        abort_unless(hash_equals($secret, (string) $request->getPassword()), Response::HTTP_UNAUTHORIZED);

        return $next($request);
    }
}

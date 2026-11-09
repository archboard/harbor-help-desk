<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the signed-in agent's locale (the session on the web, the token after `auth:api`), otherwise the best match for
     * `Accept-Language` among the available locales, otherwise the app's default. Contacts
     * have no locale, so the portal only follows the header.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userLocale = $user instanceof User ? $user->locale : null;

        $locale = $userLocale !== null && in_array($userLocale, Locales::available(), true)
            ? $userLocale
            : Locales::fromRequest($request);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}

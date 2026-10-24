<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSurveysEnabled
{
    /**
     * 404 unless the current workspace has satisfaction surveys switched on.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Workspace::current()?->settings->surveysEnabled, Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}

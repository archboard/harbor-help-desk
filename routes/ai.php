<?php

use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\ResolveWorkspace;
use App\Mcp\Servers\HarborServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Discovery metadata and dynamic client registration are public, so they are throttled.
Route::middleware('throttle:10,1')->group(function () {
    Mcp::oauthRoutes();
});

// The same access rules as /api/v1: a token granted this workspace, held by a current member.
Mcp::web('/mcp', HarborServer::class)
    ->middleware([ResolveWorkspace::class, 'auth:api', EnsureTokenCanAccessWorkspace::class, 'throttle:api'])
    ->name('mcp');

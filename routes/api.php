<?php

use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')
    ->middleware([ResolveWorkspace::class, 'auth:api', EnsureTokenCanAccessWorkspace::class])
    ->group(function () {
        Route::get('user', fn (Request $request) => new UserResource($request->user()))->name('user');
    });

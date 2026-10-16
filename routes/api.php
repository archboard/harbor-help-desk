<?php

use App\Http\Controllers\Api\V1\DeskController;
use App\Http\Controllers\Api\V1\DeskMemberController;
use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')
    ->middleware([ResolveWorkspace::class, 'auth:api', EnsureTokenCanAccessWorkspace::class])
    ->group(function () {
        Route::get('user', fn (Request $request) => new UserResource($request->user()))->name('user');

        Route::apiResource('desks', DeskController::class);
        Route::post('desks/{desk}/restore', [DeskController::class, 'restore'])->withTrashed()->name('desks.restore');

        Route::get('desks/{desk}/members', [DeskMemberController::class, 'index'])->name('desks.members.index');
        Route::put('desks/{desk}/members/{user}', [DeskMemberController::class, 'update'])->name('desks.members.update');
        Route::delete('desks/{desk}/members/{user}', [DeskMemberController::class, 'destroy'])->name('desks.members.destroy');
    });

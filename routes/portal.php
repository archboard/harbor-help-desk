<?php

use App\Http\Controllers\Portal\PortalLoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('portal.enabled')->prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalLoginController::class, 'create'])->name('login');
    Route::post('login', [PortalLoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('login/link', [PortalLoginController::class, 'show'])->name('login.show');
    Route::post('logout', [PortalLoginController::class, 'destroy'])->name('logout');

    Route::middleware('auth:contact')->group(function () {
        // ponytail: placeholder landing page until the ticket list replaces it.
        Route::get('/', fn () => Inertia::render('portal/Home'))->name('home');
    });
});

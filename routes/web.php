<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\DeskCustomFieldController;
use App\Http\Controllers\DeskMemberController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('login/magic', [MagicLinkController::class, 'store'])->middleware('throttle:6,1')->name('magic-link.store');
    Route::get('login/magic/{user}', [MagicLinkController::class, 'show'])->name('magic-link.login');
});

Route::middleware('workspace')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::resource('desks', DeskController::class)->except(['create', 'edit']);
        Route::post('desks/{desk}/restore', [DeskController::class, 'restore'])->withTrashed()->name('desks.restore');
        Route::put('desks/{desk}/members/{user}', [DeskMemberController::class, 'update'])->name('desks.members.update');
        Route::delete('desks/{desk}/members/{user}', [DeskMemberController::class, 'destroy'])->name('desks.members.destroy');
        Route::put('desks/{desk}/custom-fields', [DeskCustomFieldController::class, 'update'])->name('desks.custom-fields.update');
    });

    require __DIR__.'/settings.php';
});

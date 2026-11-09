<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\BusinessScheduleController;
use App\Http\Controllers\Settings\MacroController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SlaPolicyController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('settings/timezone', [ProfileController::class, 'updateTimezone'])->name('profile.timezone.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::middleware(RequirePassword::class)->group(function () {
        Route::get('settings/api-tokens', [ApiTokenController::class, 'edit'])->name('api-tokens.edit');
        Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('settings/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    });

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    Route::resource('settings/macros', MacroController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('settings/business-hours', BusinessScheduleController::class)->parameters(['business-hours' => 'businessSchedule'])->only(['index', 'store', 'update', 'destroy']);
    Route::resource('settings/sla-policies', SlaPolicyController::class)->parameters(['sla-policies' => 'slaPolicy'])->only(['index', 'store', 'update', 'destroy']);
});

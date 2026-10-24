<?php

use App\Enums\SatisfactionScore;
use App\Http\Controllers\Portal\AttachmentDownloadController;
use App\Http\Controllers\Portal\PortalLoginController;
use App\Http\Controllers\Portal\RateTicketController;
use App\Http\Controllers\Portal\RatingController;
use App\Http\Controllers\Portal\TicketCommentController;
use App\Http\Controllers\Portal\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('portal.enabled')->prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalLoginController::class, 'create'])->name('login');
    Route::post('login', [PortalLoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('login/link', [PortalLoginController::class, 'show'])->name('login.show');
    Route::post('logout', [PortalLoginController::class, 'destroy'])->name('logout');

    Route::middleware('auth:contact')->group(function () {
        Route::get('/', fn () => to_route('portal.tickets.index'))->name('home');
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('desks/{desk}/tickets', [TicketController::class, 'store'])->name('desks.tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
        Route::post('tickets/{ticket}/rating', RateTicketController::class)->middleware('surveys.enabled')->name('tickets.rating.store');
        Route::get('attachments/{attachment}/download', AttachmentDownloadController::class)->name('attachments.download');
    });
});

// Survey links from the satisfaction email: signed instead of signed-in, and live even with the portal off.
Route::middleware(['surveys.enabled', 'signed:relative'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('tickets/{ticket}/rate/{rating}', [RatingController::class, 'show'])->whereIn('rating', array_column(SatisfactionScore::cases(), 'value'))->name('ratings.show');
    Route::post('tickets/{ticket}/rate/{rating}', [RatingController::class, 'store'])->whereIn('rating', array_column(SatisfactionScore::cases(), 'value'))->middleware('throttle:6,1')->name('ratings.store');
});

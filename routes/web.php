<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\DeskCustomFieldController;
use App\Http\Controllers\DeskMemberController;
use App\Http\Controllers\TicketAssigneeController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDeskController;
use App\Http\Controllers\TicketReminderController;
use App\Http\Controllers\TicketTagController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tickets')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('login/magic', [MagicLinkController::class, 'store'])->middleware('throttle:6,1')->name('magic-link.store');
    Route::get('login/magic/{user}', [MagicLinkController::class, 'show'])->name('magic-link.login');
});

Route::middleware('workspace')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::redirect('dashboard', '/tickets')->name('dashboard');
        Route::get('contacts/search', [ContactController::class, 'search'])->name('contacts.search');
        Route::resource('contacts', ContactController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->withTrashed()->name('tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
        Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->withTrashed()->name('tickets.restore');

        Route::put('tickets/{ticket}/desk', [TicketDeskController::class, 'update'])->name('tickets.desk.update');
        Route::put('tickets/{ticket}/assignees', [TicketAssigneeController::class, 'update'])->name('tickets.assignees.update');
        Route::put('tickets/{ticket}/tags', [TicketTagController::class, 'update'])->name('tickets.tags.update');
        Route::post('tickets/{ticket}/reminders', [TicketReminderController::class, 'store'])->name('tickets.reminders.store');
        Route::delete('reminders/{reminder}', [TicketReminderController::class, 'destroy'])->name('reminders.destroy');

        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
        Route::patch('comments/{comment}', [TicketCommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [TicketCommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
        Route::delete('attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('attachments.download');

        Route::resource('desks', DeskController::class)->except(['create', 'edit']);
        Route::post('desks/{desk}/restore', [DeskController::class, 'restore'])->withTrashed()->name('desks.restore');
        Route::put('desks/{desk}/members/{user}', [DeskMemberController::class, 'update'])->name('desks.members.update');
        Route::delete('desks/{desk}/members/{user}', [DeskMemberController::class, 'destroy'])->name('desks.members.destroy');
        Route::put('desks/{desk}/custom-fields', [DeskCustomFieldController::class, 'update'])->name('desks.custom-fields.update');
    });

    require __DIR__.'/settings.php';
});

<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\DeskController;
use App\Http\Controllers\Api\V1\DeskCustomFieldController;
use App\Http\Controllers\Api\V1\DeskMemberController;
use App\Http\Controllers\Api\V1\SavedViewController;
use App\Http\Controllers\Api\V1\TicketAssigneeController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketDeskController;
use App\Http\Controllers\Api\V1\TicketEventController;
use App\Http\Controllers\Api\V1\TicketReminderController;
use App\Http\Controllers\Api\V1\TicketTagController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')
    ->middleware([ResolveWorkspace::class, 'auth:api', EnsureTokenCanAccessWorkspace::class])
    ->group(function () {
        Route::get('user', fn (Request $request) => new UserResource($request->user()))->name('user');

        Route::get('workspace', [WorkspaceController::class, 'show'])->name('workspace.show');
        Route::patch('workspace', [WorkspaceController::class, 'update'])->name('workspace.update');

        Route::apiResource('contacts', ContactController::class)->except('destroy');

        Route::apiResource('desks', DeskController::class);
        Route::post('desks/{desk}/restore', [DeskController::class, 'restore'])->withTrashed()->name('desks.restore');

        Route::get('desks/{desk}/members', [DeskMemberController::class, 'index'])->name('desks.members.index');
        Route::put('desks/{desk}/members/{user}', [DeskMemberController::class, 'update'])->name('desks.members.update');
        Route::delete('desks/{desk}/members/{user}', [DeskMemberController::class, 'destroy'])->name('desks.members.destroy');

        Route::apiResource('custom-fields', CustomFieldController::class)->except('show');
        Route::put('desks/{desk}/custom-fields', [DeskCustomFieldController::class, 'update'])->name('desks.custom-fields.update');

        Route::apiResource('saved-views', SavedViewController::class);

        Route::post('desks/{desk}/tickets', [TicketController::class, 'store'])->name('desks.tickets.store');
        Route::apiResource('tickets', TicketController::class)->except('store');
        Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->withTrashed()->name('tickets.restore');
        Route::put('tickets/{ticket}/desk', [TicketDeskController::class, 'update'])->name('tickets.desk.update');
        Route::put('tickets/{ticket}/assignees', [TicketAssigneeController::class, 'update'])->name('tickets.assignees.update');
        Route::put('tickets/{ticket}/tags', [TicketTagController::class, 'update'])->name('tickets.tags.update');
        Route::get('tickets/{ticket}/events', [TicketEventController::class, 'index'])->name('tickets.events.index');

        Route::apiResource('tickets.comments', TicketCommentController::class)->shallow()->except('show');
        Route::apiResource('tickets.attachments', AttachmentController::class)->shallow()->only(['index', 'store', 'destroy']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::apiResource('tickets.reminders', TicketReminderController::class)->shallow()->only(['index', 'store', 'destroy']);
    });

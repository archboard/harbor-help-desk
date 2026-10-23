<?php

use App\Http\Controllers\ApplyMacroController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\AutomationCatalogController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationRunController;
use App\Http\Controllers\AutomationTemplatesController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\DeskCustomFieldController;
use App\Http\Controllers\DeskMemberController;
use App\Http\Controllers\DuplicateAutomationController;
use App\Http\Controllers\MailboxOAuthController;
use App\Http\Controllers\MarkAllNotificationsReadController;
use App\Http\Controllers\MarkNotificationReadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReorderAutomationsController;
use App\Http\Controllers\RotateWebhookTokenController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\SetAutomationEnabledController;
use App\Http\Controllers\SetAwayController;
use App\Http\Controllers\TestAutomationController;
use App\Http\Controllers\TicketAssigneeController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketClaimController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDeskController;
use App\Http\Controllers\TicketReminderController;
use App\Http\Controllers\TicketTagController;
use App\Http\Controllers\Webhooks\AutomationWebhookController;
use App\Http\Controllers\Webhooks\MailboxPushController;
use App\Http\Controllers\Webhooks\PostmarkWebhookController;
use App\Http\Middleware\VerifyPostmarkWebhook;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tickets')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('login/magic', [MagicLinkController::class, 'store'])->middleware('throttle:6,1')->name('magic-link.store');
    Route::get('login/magic/{user}', [MagicLinkController::class, 'show'])->name('magic-link.login');
});

Route::middleware(VerifyPostmarkWebhook::class)->prefix('webhooks/postmark')->name('webhooks.postmark.')->group(function () {
    Route::post('inbound', [PostmarkWebhookController::class, 'inbound'])->name('inbound');
    Route::post('bounce', [PostmarkWebhookController::class, 'bounce'])->name('bounce');
});

Route::post('webhooks/automations/{automation}/{token}', AutomationWebhookController::class)->name('webhooks.automations');
Route::post('webhooks/google/gmail', [MailboxPushController::class, 'gmail'])->name('webhooks.google.gmail');
Route::post('webhooks/microsoft/graph', [MailboxPushController::class, 'graph'])->name('webhooks.microsoft.graph');

// On the central domain: OAuth redirect URIs can't be wildcards. The `state` names the workspace.
Route::get('mailboxes/oauth/callback', [MailboxOAuthController::class, 'callback'])->name('mailboxes.oauth.callback');

Route::middleware('workspace')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::redirect('dashboard', '/tickets')->name('dashboard');
        Route::put('availability', SetAwayController::class)->name('availability.update');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', MarkAllNotificationsReadController::class)->name('notifications.read');
        Route::post('notifications/{notification}/read', MarkNotificationReadController::class)->name('notifications.read-one');
        Route::get('contacts/search', [ContactController::class, 'search'])->name('contacts.search');
        Route::resource('contacts', ContactController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::resource('saved-views', SavedViewController::class)->only(['store', 'update', 'destroy']);
        Route::post('tickets/bulk', [TicketController::class, 'bulk'])->name('tickets.bulk');
        Route::post('tickets/claim', TicketClaimController::class)->name('tickets.claim');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('desks/{desk}/tickets', [TicketController::class, 'store'])->name('desks.tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->withTrashed()->name('tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
        Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->withTrashed()->name('tickets.restore');

        Route::put('tickets/{ticket}/desk', [TicketDeskController::class, 'update'])->name('tickets.desk.update');
        Route::put('tickets/{ticket}/assignees', [TicketAssigneeController::class, 'update'])->name('tickets.assignees.update');
        Route::put('tickets/{ticket}/tags', [TicketTagController::class, 'update'])->name('tickets.tags.update');
        Route::post('tickets/{ticket}/macros/{macro}/apply', ApplyMacroController::class)->name('tickets.macros.apply');
        Route::get('automations/catalog', AutomationCatalogController::class)->name('automations.catalog');
        Route::get('automations/templates', AutomationTemplatesController::class)->name('automations.templates');
        Route::post('automations/test', TestAutomationController::class)->name('automations.test');
        Route::post('automations/reorder', ReorderAutomationsController::class)->name('automations.reorder');
        Route::put('automations/{automation}/enabled', SetAutomationEnabledController::class)->name('automations.enabled.update');
        Route::post('automations/{automation}/duplicate', DuplicateAutomationController::class)->name('automations.duplicate');
        Route::resource('automations', AutomationController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::get('automations/{automation}/runs', [AutomationRunController::class, 'index'])->name('automations.runs.index');
        Route::get('automation-runs/{run}', [AutomationRunController::class, 'show'])->name('automation-runs.show');
        Route::post('automations/{automation}/webhook-token', RotateWebhookTokenController::class)->name('automations.webhook-token');
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

        Route::get('mailboxes/oauth/{provider}', [MailboxOAuthController::class, 'redirect'])->whereIn('provider', ['google', 'microsoft'])->name('mailboxes.oauth.redirect');
    });

    require __DIR__.'/settings.php';
    require __DIR__.'/workspace.php';
});

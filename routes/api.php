<?php

use App\Data\Users\UserData;
use App\Http\Controllers\Api\Platform\WorkspaceController as PlatformWorkspaceController;
use App\Http\Controllers\Api\V1\ApplyMacroController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AutomationCatalogController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\AutomationRunController;
use App\Http\Controllers\Api\V1\AutomationTemplatesController;
use App\Http\Controllers\Api\V1\BusinessScheduleController;
use App\Http\Controllers\Api\V1\CancelAutomationRunController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\DeskController;
use App\Http\Controllers\Api\V1\DeskCustomFieldController;
use App\Http\Controllers\Api\V1\DeskMemberController;
use App\Http\Controllers\Api\V1\DownloadExportController;
use App\Http\Controllers\Api\V1\DuplicateAutomationController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MacroController;
use App\Http\Controllers\Api\V1\MailboxController;
use App\Http\Controllers\Api\V1\MarkNotificationsReadController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\ReorderAutomationsController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RequestContactExportController;
use App\Http\Controllers\Api\V1\RotateWebhookTokenController;
use App\Http\Controllers\Api\V1\SavedViewController;
use App\Http\Controllers\Api\V1\SetAutomationEnabledController;
use App\Http\Controllers\Api\V1\SetAwayController;
use App\Http\Controllers\Api\V1\SlaPolicyController;
use App\Http\Controllers\Api\V1\TestAutomationController;
use App\Http\Controllers\Api\V1\TicketAssigneeController;
use App\Http\Controllers\Api\V1\TicketClaimController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketDeskController;
use App\Http\Controllers\Api\V1\TicketEventController;
use App\Http\Controllers\Api\V1\TicketReminderController;
use App\Http\Controllers\Api\V1\TicketTagController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceSettingsController;
use App\Http\Controllers\Api\V1\WorkspaceTicketEventController;
use App\Http\Middleware\EnsurePlatformToken;
use App\Http\Middleware\EnsureTokenCanAccessWorkspace;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Landlord: the platform creates and removes workspaces, on any host and outside any workspace.
Route::prefix('platform')->name('api.platform.')->middleware(EnsurePlatformToken::class)->group(function () {
    Route::apiResource('workspaces', PlatformWorkspaceController::class)->only(['store', 'update', 'destroy']);
});

Route::prefix('v1')->name('api.v1.')
    ->middleware([ResolveWorkspace::class, 'auth:api', EnsureTokenCanAccessWorkspace::class])
    ->group(function () {
        Route::get('user', fn (Request $request): UserData => UserData::from($request->user()))->name('user');

        Route::get('workspace', [WorkspaceController::class, 'show'])->name('workspace.show');
        Route::patch('workspace', [WorkspaceController::class, 'update'])->name('workspace.update');
        Route::patch('workspace/settings', [WorkspaceSettingsController::class, 'update'])->name('workspace.settings.update');

        Route::put('availability', SetAwayController::class)->name('availability.update');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', MarkNotificationsReadController::class)->name('notifications.read');
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

        Route::apiResource('contacts', ContactController::class)->except('destroy');

        Route::apiResource('desks', DeskController::class);
        Route::post('desks/{desk}/restore', [DeskController::class, 'restore'])->withTrashed()->name('desks.restore');

        Route::get('desks/{desk}/members', [DeskMemberController::class, 'index'])->name('desks.members.index');
        Route::put('desks/{desk}/members/{user}', [DeskMemberController::class, 'update'])->name('desks.members.update');
        Route::delete('desks/{desk}/members/{user}', [DeskMemberController::class, 'destroy'])->name('desks.members.destroy');

        Route::apiResource('custom-fields', CustomFieldController::class)->except('show');
        Route::put('desks/{desk}/custom-fields', [DeskCustomFieldController::class, 'update'])->name('desks.custom-fields.update');

        Route::apiResource('saved-views', SavedViewController::class);
        Route::apiResource('macros', MacroController::class);
        Route::get('automations/catalog', AutomationCatalogController::class)->name('automations.catalog');
        Route::get('automations/templates', AutomationTemplatesController::class)->name('automations.templates');
        Route::post('automations/reorder', ReorderAutomationsController::class)->name('automations.reorder');
        Route::post('automations/{automation}/duplicate', DuplicateAutomationController::class)->name('automations.duplicate');
        Route::put('automations/{automation}/enabled', SetAutomationEnabledController::class)->name('automations.enabled.update');
        Route::post('automations/{automation}/webhook-token', RotateWebhookTokenController::class)->name('automations.webhook-token');
        Route::post('automations/test', TestAutomationController::class)->name('automations.test');
        Route::get('automations/runs', [AutomationRunController::class, 'index'])->name('automation-runs.index');
        Route::get('automations/{automation}/runs', [AutomationRunController::class, 'index'])->name('automations.runs.index');
        Route::get('automation-runs/{run}', [AutomationRunController::class, 'show'])->name('automation-runs.show');
        Route::post('automation-runs/{run}/cancel', CancelAutomationRunController::class)->name('automation-runs.cancel');
        Route::apiResource('automations', AutomationController::class);
        Route::apiResource('business-schedules', BusinessScheduleController::class);
        Route::apiResource('sla-policies', SlaPolicyController::class)->parameters(['sla-policies' => 'slaPolicy']);

        Route::apiResource('mailboxes', MailboxController::class);

        Route::post('contacts/{contact}/export', RequestContactExportController::class)->name('contacts.export');
        Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('exports/{export}/download', DownloadExportController::class)->name('exports.download');

        Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');
        Route::get('ticket-events', [WorkspaceTicketEventController::class, 'index'])->name('ticket-events.index');

        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

        Route::post('desks/{desk}/tickets', [TicketController::class, 'store'])->name('desks.tickets.store');
        Route::post('tickets/bulk', [TicketController::class, 'bulk'])->name('tickets.bulk');
        Route::post('tickets/claim', TicketClaimController::class)->name('tickets.claim');
        Route::apiResource('tickets', TicketController::class)->except('store');
        Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->withTrashed()->name('tickets.restore');
        Route::put('tickets/{ticket}/desk', [TicketDeskController::class, 'update'])->name('tickets.desk.update');
        Route::put('tickets/{ticket}/assignees', [TicketAssigneeController::class, 'update'])->name('tickets.assignees.update');
        Route::put('tickets/{ticket}/tags', [TicketTagController::class, 'update'])->name('tickets.tags.update');
        Route::post('tickets/{ticket}/macros/{macro}/apply', ApplyMacroController::class)->name('tickets.macros.apply');
        Route::get('tickets/{ticket}/events', [TicketEventController::class, 'index'])->name('tickets.events.index');

        Route::apiResource('tickets.comments', TicketCommentController::class)->shallow()->except('show');
        Route::apiResource('tickets.attachments', AttachmentController::class)->shallow()->only(['index', 'store', 'destroy']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::apiResource('tickets.reminders', TicketReminderController::class)->shallow()->only(['index', 'store', 'destroy']);
    });

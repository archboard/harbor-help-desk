<?php

use App\Http\Controllers\Workspace\AttachmentsController;
use App\Http\Controllers\Workspace\AuditLogController;
use App\Http\Controllers\Workspace\AutomationsController;
use App\Http\Controllers\Workspace\CustomFieldController;
use App\Http\Controllers\Workspace\DataPrivacyController;
use App\Http\Controllers\Workspace\EmailController;
use App\Http\Controllers\Workspace\GeneralController;
use App\Http\Controllers\Workspace\ImportWorkspaceController;
use App\Http\Controllers\Workspace\PortalController;
use App\Http\Controllers\Workspace\RequestWorkspaceExportController;
use App\Http\Controllers\Workspace\RotateWebhookSecretController;
use App\Http\Controllers\Workspace\SendWebhookPingController;
use App\Http\Controllers\Workspace\SettingsController;
use App\Http\Controllers\Workspace\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('workspace')->name('workspace.')->group(function () {
    Route::redirect('/', '/workspace/general');

    Route::get('general', [GeneralController::class, 'edit'])->name('general.edit');
    Route::patch('general', [GeneralController::class, 'update'])->name('general.update');

    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::resource('email', EmailController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['email' => 'mailbox']);

    Route::get('attachments', [AttachmentsController::class, 'edit'])->name('attachments.edit');

    Route::get('data-privacy', [DataPrivacyController::class, 'index'])->name('data-privacy.index');
    Route::post('exports', RequestWorkspaceExportController::class)->name('exports.store');
    Route::post('import', ImportWorkspaceController::class)->name('import.store');

    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('portal', [PortalController::class, 'edit'])->name('portal.edit');

    Route::get('automations', [AutomationsController::class, 'edit'])->name('automations.edit');

    Route::resource('custom-fields', CustomFieldController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['custom-fields' => 'customField']);

    Route::resource('webhooks', WebhookController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('webhooks/{webhook}/secret', RotateWebhookSecretController::class)->name('webhooks.secret');
    Route::post('webhooks/{webhook}/ping', SendWebhookPingController::class)->name('webhooks.ping');
});

<?php

use App\Http\Controllers\Workspace\AttachmentsController;
use App\Http\Controllers\Workspace\CustomFieldController;
use App\Http\Controllers\Workspace\EmailController;
use App\Http\Controllers\Workspace\GeneralController;
use App\Http\Controllers\Workspace\SettingsController;
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

    Route::resource('custom-fields', CustomFieldController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['custom-fields' => 'customField']);
});

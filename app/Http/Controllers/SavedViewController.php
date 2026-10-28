<?php

namespace App\Http\Controllers;

use App\Actions\SavedViews\CreateSavedView;
use App\Actions\SavedViews\DeleteSavedView;
use App\Actions\SavedViews\UpdateSavedView;
use App\Http\Flash;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, CreateSavedView $createSavedView): RedirectResponse
    {
        $createSavedView->handle($user, $request->all());

        Flash::success(__('View saved.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, SavedView $savedView, UpdateSavedView $updateSavedView): RedirectResponse
    {
        $updateSavedView->handle($user, $savedView, $request->all());

        Flash::success(__('View updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, SavedView $savedView, DeleteSavedView $deleteSavedView): RedirectResponse
    {
        $deleteSavedView->handle($user, $savedView);

        Flash::success(__('View deleted.'));

        return back();
    }
}

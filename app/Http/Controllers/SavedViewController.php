<?php

namespace App\Http\Controllers;

use App\Actions\SavedViews\CreateSavedView;
use App\Actions\SavedViews\DeleteSavedView;
use App\Actions\SavedViews\UpdateSavedView;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SavedViewController extends Controller
{
    public function store(Request $request, CreateSavedView $createSavedView): RedirectResponse
    {
        $createSavedView->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View saved.')]);

        return back();
    }

    public function update(Request $request, SavedView $savedView, UpdateSavedView $updateSavedView): RedirectResponse
    {
        $updateSavedView->handle($request->user(), $savedView, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View updated.')]);

        return back();
    }

    public function destroy(Request $request, SavedView $savedView, DeleteSavedView $deleteSavedView): RedirectResponse
    {
        $deleteSavedView->handle($request->user(), $savedView);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View deleted.')]);

        return back();
    }
}

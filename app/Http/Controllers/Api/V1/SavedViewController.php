<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SavedViews\CreateSavedView;
use App\Actions\SavedViews\DeleteSavedView;
use App\Actions\SavedViews\UpdateSavedView;
use App\Data\SavedViews\SavedViewData;
use App\Http\Controllers\Controller;
use App\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SavedViewController extends Controller
{
    /**
     * The user's own views and the ones shared with the workspace.
     *
     * @return Collection<int, SavedViewData>
     */
    public function index(Request $request): Collection
    {
        Gate::authorize('viewAny', SavedView::class);

        return SavedViewData::collect(SavedView::visibleTo($request->user())->with('user')->orderBy('name')->get());
    }

    public function store(Request $request, CreateSavedView $createSavedView): SavedViewData
    {
        return SavedViewData::from($createSavedView->handle($request->user(), $request->all()));
    }

    public function show(SavedView $savedView): SavedViewData
    {
        Gate::authorize('view', $savedView);

        return SavedViewData::from($savedView->load('user'));
    }

    public function update(Request $request, SavedView $savedView, UpdateSavedView $updateSavedView): SavedViewData
    {
        return SavedViewData::from($updateSavedView->handle($request->user(), $savedView, $request->all()));
    }

    public function destroy(Request $request, SavedView $savedView, DeleteSavedView $deleteSavedView): Response
    {
        $deleteSavedView->handle($request->user(), $savedView);

        return response()->noContent();
    }
}

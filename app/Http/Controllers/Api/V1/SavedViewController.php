<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SavedViews\CreateSavedView;
use App\Actions\SavedViews\DeleteSavedView;
use App\Actions\SavedViews\UpdateSavedView;
use App\Http\Controllers\Controller;
use App\Http\Resources\SavedViewResource;
use App\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SavedViewController extends Controller
{
    /**
     * The user's own views and the ones shared with the workspace.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SavedView::class);

        return SavedViewResource::collection(SavedView::visibleTo($request->user())->with('user')->orderBy('name')->get());
    }

    public function store(Request $request, CreateSavedView $createSavedView): SavedViewResource
    {
        return new SavedViewResource($createSavedView->handle($request->user(), $request->all()));
    }

    public function show(SavedView $savedView): SavedViewResource
    {
        Gate::authorize('view', $savedView);

        return new SavedViewResource($savedView->load('user'));
    }

    public function update(Request $request, SavedView $savedView, UpdateSavedView $updateSavedView): SavedViewResource
    {
        return new SavedViewResource($updateSavedView->handle($request->user(), $savedView, $request->all()));
    }

    public function destroy(Request $request, SavedView $savedView, DeleteSavedView $deleteSavedView): Response
    {
        $deleteSavedView->handle($request->user(), $savedView);

        return response()->noContent();
    }
}

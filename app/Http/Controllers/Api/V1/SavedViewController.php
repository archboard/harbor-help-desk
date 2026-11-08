<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SavedViews\CreateSavedView;
use App\Actions\SavedViews\DeleteSavedView;
use App\Actions\SavedViews\UpdateSavedView;
use App\Data\SavedViews\CreateSavedViewData;
use App\Data\SavedViews\SavedViewData;
use App\Data\SavedViews\UpdateSavedViewData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function index(#[CurrentUser] User $user): Collection
    {
        Gate::authorize('viewAny', SavedView::class);

        return SavedViewData::collect(SavedView::visibleTo($user)->with('user')->orderBy('name')->get());
    }

    #[ApiBody(CreateSavedViewData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateSavedView $createSavedView): SavedViewData
    {
        return SavedViewData::from($createSavedView->handle($user, $request->all()));
    }

    public function show(SavedView $savedView): SavedViewData
    {
        Gate::authorize('view', $savedView);

        return SavedViewData::from($savedView->load('user'));
    }

    #[ApiBody(UpdateSavedViewData::class)]
    public function update(Request $request, #[CurrentUser] User $user, SavedView $savedView, UpdateSavedView $updateSavedView): SavedViewData
    {
        return SavedViewData::from($updateSavedView->handle($user, $savedView, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, SavedView $savedView, DeleteSavedView $deleteSavedView): Response
    {
        $deleteSavedView->handle($user, $savedView);

        return response()->noContent();
    }
}

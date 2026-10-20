<?php

namespace App\Actions\SavedViews;

use App\Data\SavedViews\UpdateSavedViewData;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class UpdateSavedView
{
    /**
     * Rename the view, replace its filters, or (owner only) share or unshare it.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, SavedView $savedView, array $input): SavedView
    {
        Gate::forUser($actor)->authorize('update', $savedView);

        $data = UpdateSavedViewData::validateAndCreate($input);

        if (! $data->isShared instanceof Optional && $data->isShared !== $savedView->is_shared) {
            Gate::forUser($actor)->authorize('share', $savedView);
        }

        $savedView->update($data->filled());

        return $savedView;
    }
}

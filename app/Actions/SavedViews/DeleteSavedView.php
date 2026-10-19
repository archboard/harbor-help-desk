<?php

namespace App\Actions\SavedViews;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteSavedView
{
    public function handle(User $actor, SavedView $savedView): void
    {
        Gate::forUser($actor)->authorize('delete', $savedView);

        $savedView->delete();
    }
}

<?php

namespace App\Actions\SavedViews;

use App\Data\SavedViews\CreateSavedViewData;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class CreateSavedView
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): SavedView
    {
        Gate::forUser($actor)->authorize('create', SavedView::class);

        $data = CreateSavedViewData::validateAndCreate($input);

        return SavedView::create([
            'user_id' => $actor->getKey(),
            'name' => $data->name,
            'filters' => $data->filters,
            'is_shared' => $data->isShared instanceof Optional ? false : $data->isShared,
        ]);
    }
}

<?php

namespace App\Actions\SavedViews;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateSavedView
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): SavedView
    {
        Gate::forUser($actor)->authorize('create', SavedView::class);

        $validated = Validator::make($input, SavedViewRules::for($input))->validate();

        return SavedView::create([
            'user_id' => $actor->getKey(),
            'name' => $validated['name'],
            'filters' => $validated['filters'],
            'is_shared' => $validated['is_shared'] ?? false,
        ]);
    }
}

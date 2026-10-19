<?php

namespace App\Actions\SavedViews;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

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

        $rules = array_map(fn (array $rules): array => ['sometimes', ...$rules], SavedViewRules::for($input));
        $validated = Validator::make($input, $rules)->validate();

        if (isset($validated['is_shared']) && (bool) $validated['is_shared'] !== $savedView->is_shared) {
            Gate::forUser($actor)->authorize('share', $savedView);
        }

        $savedView->update(Arr::only($validated, ['name', 'filters', 'is_shared']));

        return $savedView;
    }
}

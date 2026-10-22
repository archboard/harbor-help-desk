<?php

namespace App\Actions\Automations;

use App\Data\Automations\ReorderAutomationsData;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReorderAutomations
{
    /**
     * Give the automations the positions they already hold, in the new order, so a lead
     * reordering only their own desk's automations leaves the others where they are.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Automation>
     */
    public function handle(User $actor, array $input): Collection
    {
        $data = ReorderAutomationsData::validateAndCreate($input);

        $automations = Automation::query()->whereKey($data->ids)->get()->keyBy('id');

        if ($automations->pluck('trigger_type')->unique()->count() > 1) {
            throw ValidationException::withMessages(['ids' => __('Only automations with the same trigger can be reordered together.')]);
        }

        foreach ($automations as $automation) {
            Gate::forUser($actor)->authorize('update', $automation);
        }

        $positions = $automations->pluck('position')->sort()->values();

        return DB::transaction(fn (): Collection => collect($data->ids)->values()->map(function (string $id, int $index) use ($automations, $positions): Automation {
            $automation = $automations[$id];
            $automation->update(['position' => $positions[$index]]);

            return $automation;
        }));
    }
}

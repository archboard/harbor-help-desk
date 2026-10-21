<?php

namespace App\Actions\Macros;

use App\Data\Macros\UpdateMacroData;
use App\Models\Macro;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateMacro
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Macro $macro, array $input): Macro
    {
        Gate::forUser($actor)->authorize('update', $macro);

        $data = UpdateMacroData::validateAndCreate($input);

        $macro->fill($data->filled());

        if (blank($macro->body) && blank($macro->actions)) {
            throw ValidationException::withMessages(['body' => __('A macro needs a reply or some actions.')]);
        }

        $macro->save();

        return $macro;
    }
}

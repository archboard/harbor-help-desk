<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateDesk
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Desk $desk, array $input): Desk
    {
        Gate::forUser($actor)->authorize('update', $desk);

        $validated = Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', ...DeskSlugRules::for($desk->workspace, $desk)],
        ])->validate();

        $desk->update($validated);

        return $desk;
    }
}

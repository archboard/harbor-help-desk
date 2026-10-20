<?php

namespace App\Actions\Desks;

use App\Data\Desks\UpdateDeskData;
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

        $data = UpdateDeskData::validateAndCreate($input);
        Validator::make($input, ['slug' => ['sometimes', DeskSlugRules::unique($desk->workspace, $desk)]])->validate();

        $desk->update($data->filled());

        return $desk;
    }
}

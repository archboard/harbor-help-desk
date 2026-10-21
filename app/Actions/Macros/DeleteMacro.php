<?php

namespace App\Actions\Macros;

use App\Models\Macro;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteMacro
{
    public function handle(User $actor, Macro $macro): void
    {
        Gate::forUser($actor)->authorize('delete', $macro);

        $macro->delete();
    }
}

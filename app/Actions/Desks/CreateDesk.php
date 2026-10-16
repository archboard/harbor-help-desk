<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateDesk
{
    /**
     * Create a desk in the current workspace. The slug defaults to one made from the name.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Desk
    {
        Gate::forUser($actor)->authorize('create', Desk::class);

        $input['slug'] ??= Str::slug((string) ($input['name'] ?? ''));

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => DeskSlugRules::for(Workspace::current()),
        ])->validate();

        return Desk::create($validated);
    }
}

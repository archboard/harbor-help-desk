<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateContact
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Contact
    {
        Gate::forUser($actor)->authorize('create', Contact::class);

        return Contact::create(Validator::make($input, ContactRules::for())->validate());
    }
}

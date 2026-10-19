<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateContact
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Contact $contact, array $input): Contact
    {
        Gate::forUser($actor)->authorize('update', $contact);

        $rules = array_map(fn (array $rules): array => ['sometimes', ...$rules], ContactRules::for($contact));

        $contact->update(Validator::make($input, $rules)->validate());

        return $contact;
    }
}

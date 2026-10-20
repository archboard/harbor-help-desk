<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\UpdateContactData;
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

        $data = UpdateContactData::validateAndCreate($input);
        Validator::make($input, ['email' => ['sometimes', ContactRules::uniqueEmail($contact)]])->validate();

        $contact->update($data->filled());

        return $contact;
    }
}

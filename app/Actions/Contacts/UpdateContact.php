<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\UpdateContactData;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateContact
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Contact $contact, array $input): Contact
    {
        Gate::forUser($actor)->authorize('update', $contact);

        $data = UpdateContactData::validateAndCreateWith($input, ['email' => ['sometimes', ContactRules::uniqueEmail($contact)]]);

        $contact->update($data->filled());

        return $contact;
    }
}

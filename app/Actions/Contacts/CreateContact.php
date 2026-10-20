<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\CreateContactData;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateContact
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Contact
    {
        Gate::forUser($actor)->authorize('create', Contact::class);

        return Contact::create(CreateContactData::validateAndCreate($input)->filled());
    }
}

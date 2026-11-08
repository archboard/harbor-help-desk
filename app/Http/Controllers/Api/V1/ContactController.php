<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\SearchContacts;
use App\Actions\Contacts\UpdateContact;
use App\Data\Contacts\ContactData;
use App\Data\Contacts\CreateContactData;
use App\Data\Contacts\SearchContactsData;
use App\Data\Contacts\UpdateContactData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiQuery;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class ContactController extends Controller
{
    /**
     * The workspace's contacts, filtered by `search` (name or email).
     *
     * @return PaginatedDataCollection<int, ContactData>
     */
    #[ApiQuery(SearchContactsData::class)]
    public function index(Request $request, #[CurrentUser] User $user, SearchContacts $searchContacts): PaginatedDataCollection
    {
        return ContactData::collect($searchContacts->handle($user, $request->query())->paginate(), PaginatedDataCollection::class);
    }

    #[ApiBody(CreateContactData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateContact $createContact): ContactData
    {
        return ContactData::from($createContact->handle($user, $request->all()));
    }

    public function show(Contact $contact): ContactData
    {
        Gate::authorize('view', $contact);

        return ContactData::from($contact);
    }

    #[ApiBody(UpdateContactData::class)]
    public function update(Request $request, #[CurrentUser] User $user, Contact $contact, UpdateContact $updateContact): ContactData
    {
        return ContactData::from($updateContact->handle($user, $contact, $request->all()));
    }
}

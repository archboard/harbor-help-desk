<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\SearchContacts;
use App\Actions\Contacts\UpdateContact;
use App\Data\Contacts\ContactData;
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
    public function index(Request $request, #[CurrentUser] User $user, SearchContacts $searchContacts): PaginatedDataCollection
    {
        return ContactData::collect($searchContacts->handle($user, $request->query())->paginate(), PaginatedDataCollection::class);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateContact $createContact): ContactData
    {
        return ContactData::from($createContact->handle($user, $request->all()));
    }

    public function show(Contact $contact): ContactData
    {
        Gate::authorize('view', $contact);

        return ContactData::from($contact);
    }

    public function update(Request $request, #[CurrentUser] User $user, Contact $contact, UpdateContact $updateContact): ContactData
    {
        return ContactData::from($updateContact->handle($user, $contact, $request->all()));
    }
}

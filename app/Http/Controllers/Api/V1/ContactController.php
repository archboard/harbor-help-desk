<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\SearchContacts;
use App\Actions\Contacts\UpdateContact;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    /**
     * The workspace's contacts, filtered by `search` (name or email).
     */
    public function index(Request $request, SearchContacts $searchContacts): AnonymousResourceCollection
    {
        return ContactResource::collection($searchContacts->handle($request->user(), $request->query())->paginate());
    }

    public function store(Request $request, CreateContact $createContact): ContactResource
    {
        return new ContactResource($createContact->handle($request->user(), $request->all()));
    }

    public function show(Contact $contact): ContactResource
    {
        Gate::authorize('view', $contact);

        return new ContactResource($contact);
    }

    public function update(Request $request, Contact $contact, UpdateContact $updateContact): ContactResource
    {
        return new ContactResource($updateContact->handle($request->user(), $contact, $request->all()));
    }
}

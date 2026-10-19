<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\SearchContacts;
use App\Actions\Contacts\UpdateContact;
use App\Actions\Tickets\SearchTickets;
use App\Http\Resources\ContactResource;
use App\Http\Resources\TicketResource;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, SearchContacts $searchContacts): Response
    {
        $filters = $request->only('search');

        return Inertia::render('contacts/Index', [
            'contacts' => Inertia::scroll(ContactResource::collection($searchContacts->handle($request->user(), $filters)->paginate(50))),
            'filters' => (object) $filters,
        ]);
    }

    /**
     * The first few matching contacts, for a requester picker.
     */
    public function search(Request $request, SearchContacts $searchContacts): AnonymousResourceCollection
    {
        return ContactResource::collection($searchContacts->handle($request->user(), $request->only('search'))->limit(10)->get());
    }

    public function store(Request $request, CreateContact $createContact): RedirectResponse
    {
        $contact = $createContact->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * The contact and the tickets they requested that the user can see.
     */
    public function show(Request $request, Contact $contact, SearchTickets $searchTickets): Response
    {
        Gate::authorize('view', $contact);

        $tickets = $searchTickets->handle($request->user(), ['requester_id' => $contact->id])
            ->with(['desk', 'assignees'])
            ->paginate(50);

        return Inertia::render('contacts/Show', [
            'contact' => new ContactResource($contact),
            'tickets' => Inertia::scroll(TicketResource::collection($tickets)),
        ]);
    }

    public function update(Request $request, Contact $contact, UpdateContact $updateContact): RedirectResponse
    {
        $updateContact->handle($request->user(), $contact, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\SearchContacts;
use App\Actions\Contacts\UpdateContact;
use App\Actions\Tickets\SearchTickets;
use App\Data\Contacts\ContactData;
use App\Data\Tickets\TicketData;
use App\Http\Flash;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, SearchContacts $searchContacts): Response
    {
        $filters = $request->only('search');

        return Inertia::render('contacts/Index', [
            'contacts' => Inertia::scroll(ContactData::collect($searchContacts->handle($request->user(), $filters)->paginate(50))),
            'filters' => (object) $filters,
        ]);
    }

    /**
     * The first few matching contacts, for a requester picker.
     *
     * @return Collection<int, ContactData>
     */
    public function search(Request $request, SearchContacts $searchContacts): Collection
    {
        return ContactData::collect($searchContacts->handle($request->user(), $request->only('search'))->limit(10)->get());
    }

    public function store(Request $request, CreateContact $createContact): RedirectResponse
    {
        $contact = $createContact->handle($request->user(), $request->all());

        Flash::success(__('Contact created.'));

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
            'contact' => ContactData::from($contact),
            'tickets' => Inertia::scroll(TicketData::collect($tickets)),
        ]);
    }

    public function update(Request $request, Contact $contact, UpdateContact $updateContact): RedirectResponse
    {
        $updateContact->handle($request->user(), $contact, $request->all());

        Flash::success(__('Contact updated.'));

        return back();
    }
}

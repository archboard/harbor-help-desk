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
use App\Models\Export;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, SearchContacts $searchContacts): Response
    {
        $filters = $request->only('search');

        return Inertia::render('contacts/Index', [
            'contacts' => Inertia::scroll(ContactData::collect($searchContacts->handle($user, $filters)->paginate(50))),
            'filters' => (object) $filters,
        ]);
    }

    /**
     * The first few matching contacts, for a requester picker.
     *
     * @return Collection<int, ContactData>
     */
    public function search(Request $request, #[CurrentUser] User $user, SearchContacts $searchContacts): Collection
    {
        return ContactData::collect($searchContacts->handle($user, $request->only('search'))->limit(10)->get());
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateContact $createContact): RedirectResponse
    {
        $contact = $createContact->handle($user, $request->all());

        Flash::success(__('Contact created.'));

        return to_route('contacts.show', $contact);
    }

    /**
     * The contact and the tickets they requested that the user can see.
     */
    public function show(#[CurrentUser] User $user, Contact $contact, SearchTickets $searchTickets): Response
    {
        Gate::authorize('view', $contact);

        $tickets = $searchTickets->handle($user, ['requester_id' => $contact->id])
            ->with(['desk', 'assignees'])
            ->paginate(50);

        return Inertia::render('contacts/Show', [
            'contact' => ContactData::from($contact),
            'canExport' => $user->can('create', Export::class),
            'tickets' => Inertia::scroll(TicketData::collect($tickets)),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, Contact $contact, UpdateContact $updateContact): RedirectResponse
    {
        $updateContact->handle($user, $contact, $request->all());

        Flash::success(__('Contact updated.'));

        return back();
    }
}

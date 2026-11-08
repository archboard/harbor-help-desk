<?php

namespace App\Mcp\Tools;

use App\Data\Contacts\ContactData;
use App\Data\Tickets\TicketData;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one contact by `ctc_…` id, with their 10 most recently updated tickets on desks the agent can see.')]
#[IsReadOnly]
class GetContactTool extends Tool
{
    public function handle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (string) $request->get('contact');

        $contact = Contact::find($id);

        if ($contact === null) {
            return Response::error("Contact {$id} not found.");
        }

        Gate::forUser($user)->authorize('view', $contact);

        return Response::json([
            'contact' => ContactData::from($contact),
            'recent_tickets' => TicketData::collect(
                Ticket::visibleTo($user)->where('requester_id', $contact->id)->latest('updated_at')->limit(10)->get()
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact' => $schema->string()->description('Contact `ctc_…` id.')->required(),
        ];
    }
}

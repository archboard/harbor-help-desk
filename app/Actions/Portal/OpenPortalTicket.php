<?php

namespace App\Actions\Portal;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\NewTicketRateLimit;
use App\Data\Portal\OpenPortalTicketData;
use App\Enums\TicketSource;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class OpenPortalTicket
{
    public function __construct(
        protected CreateTicket $createTicket,
        protected NewTicketRateLimit $rateLimit,
    ) {}

    /**
     * Open a ticket on the desk for the signed-in contact, with `subject`, `body` and `attachments`. Shares the
     * hourly new-ticket limit with email for the contact's address.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Contact $contact, Desk $desk, array $input): Ticket
    {
        $data = OpenPortalTicketData::validateAndCreate($input);

        if ($this->rateLimit->isExceeded(Workspace::current(), $contact->email)) {
            throw ValidationException::withMessages(['subject' => __('You have opened too many tickets recently. Please try again later.')]);
        }

        return $this->createTicket->handle($contact, $desk, [
            ...$data->filled(),
            'requester_id' => $contact->getKey(),
        ], TicketSource::PORTAL);
    }
}

<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkUpdateTickets
{
    private const array OPERATIONS = ['status', 'priority', 'assignee_ids', 'add_tag', 'desk_id', 'delete'];

    public function __construct(
        protected UpdateTicket $updateTicket,
        protected SyncTicketAssignees $syncTicketAssignees,
        protected SyncTicketTags $syncTicketTags,
        protected MoveTicket $moveTicket,
        protected DeleteTicket $deleteTicket,
    ) {}

    /**
     * Apply one operation to every ticket in `ticket_ids` through the per-ticket actions, so
     * each ticket is authorized and recorded as usual. All or nothing: if any ticket fails,
     * none change.
     *
     * Operations: `status`, `priority`, `assignee_ids` (replaces the assignees), `add_tag` (a
     * tag name), `desk_id` or `delete`.
     *
     * @param  array<string, mixed>  $input
     * @return int The number of tickets changed.
     */
    public function handle(User $actor, array $input): int
    {
        $validated = Validator::make($input, [
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['string', 'distinct'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'assignee_ids' => ['sometimes', 'present', 'array'],
            'assignee_ids.*' => ['string', 'distinct'],
            'add_tag' => ['sometimes', 'string', 'max:255'],
            'desk_id' => ['sometimes', 'string'],
            'delete' => ['sometimes', 'accepted'],
        ])->validate();

        $operations = array_values(array_intersect(self::OPERATIONS, array_keys($validated)));

        if (count($operations) !== 1) {
            throw ValidationException::withMessages(['operation' => __('Choose exactly one change to make.')]);
        }

        $operation = $operations[0];

        $tickets = Ticket::visibleTo($actor)->whereKey($validated['ticket_ids'])->with(['desk', 'tags'])->orderBy('number')->get();

        if ($tickets->count() !== count($validated['ticket_ids'])) {
            throw ValidationException::withMessages(['ticket_ids' => __('Some of these tickets can’t be found.')]);
        }

        $desk = null;

        try {
            DB::transaction(function () use ($actor, $tickets, $operation, $validated, &$desk): void {
                foreach ($tickets as $ticket) {
                    match ($operation) {
                        'status', 'priority' => $this->updateTicket->handle($actor, $ticket, Arr::only($validated, [$operation])),
                        'assignee_ids' => $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => $validated['assignee_ids']]),
                        'add_tag' => $this->syncTicketTags->handle($actor, $ticket, [
                            'names' => [...$ticket->tags->map(fn ($tag): string => $tag->name)->all(), $validated['add_tag']],
                        ]),
                        'desk_id' => $this->moveTicket->handle($actor, $ticket, $desk ??= $this->findDesk($validated['desk_id'])),
                        'delete' => $this->deleteTicket->handle($actor, $ticket),
                    };
                }
            });
        } catch (ValidationException $exception) {
            // The per-ticket actions name their own fields (e.g. `user_ids`); report against ours.
            throw ValidationException::withMessages([$operation => Arr::flatten($exception->errors())]);
        }

        return $tickets->count();
    }

    protected function findDesk(string $id): Desk
    {
        return Desk::query()->whereKey($id)->first()
            ?? throw ValidationException::withMessages(['desk_id' => __('That desk can’t be found.')]);
    }
}

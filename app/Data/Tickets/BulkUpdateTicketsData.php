<?php

namespace App\Data\Tickets;

use App\Data\InputData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Spatie\LaravelData\Optional;

/**
 * Exactly one operation besides `ticket_ids`: `status`, `priority`, `assignee_ids` (replaces
 * the assignees), `add_tag` (a tag name), `desk_id` or `delete`.
 */
class BulkUpdateTicketsData extends InputData
{
    public const array OPERATIONS = ['status', 'priority', 'assignee_ids', 'add_tag', 'desk_id', 'delete'];

    /**
     * @param  list<string>  $ticketIds
     * @param  list<string>|Optional  $assigneeIds
     */
    public function __construct(
        public array $ticketIds,
        public TicketStatus|Optional $status,
        public TicketPriority|Optional $priority,
        public array|Optional $assigneeIds,
        public string|Optional $addTag,
        public string|Optional $deskId,
        public bool|Optional $delete,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['string', 'distinct'],
            'assignee_ids' => ['sometimes', 'present', 'array'],
            'assignee_ids.*' => ['string', 'distinct'],
            'add_tag' => ['sometimes', 'string', 'max:255'],
            'delete' => ['sometimes', 'accepted'],
        ];
    }
}

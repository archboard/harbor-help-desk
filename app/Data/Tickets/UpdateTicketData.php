<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\UpdateTicket;
use App\Data\InputData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * The desk's custom fields are checked by {@see UpdateTicket}.
 */
class UpdateTicketData extends InputData
{
    /**
     * @param  array<string, mixed>|Optional  $customFields  Values keyed by custom field id; null removes one.
     */
    public function __construct(
        public string|Optional $subject,
        public TicketStatus|Optional $status,
        public TicketPriority|Optional $priority,
        public string|Optional|null $dueAt,
        public array|Optional $customFields,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TicketPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

<?php

namespace App\Data\Tickets;

use App\Data\InputData;
use Spatie\LaravelData\Optional;

class SetTicketReminderData extends InputData
{
    /**
     * @param  bool|Optional  $forAssignees  A ticket-level reminder for whoever is assigned, instead of a personal one.
     */
    public function __construct(
        public string $remindAt,
        public string|Optional|null $note,
        public bool|Optional $forAssignees,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'remind_at' => ['required', 'date', 'after:now'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

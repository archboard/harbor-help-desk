<?php

namespace App\Data\Macros;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Spatie\LaravelData\Data;

class MacroActionsData extends Data
{
    /**
     * @param  list<string>  $addTags
     * @param  list<string>  $removeTags
     */
    public function __construct(
        public ?TicketStatus $status,
        public ?TicketPriority $priority,
        public array $addTags,
        public array $removeTags,
        public bool $assignToMe,
    ) {}

    /**
     * @param  array<string, mixed>  $actions
     */
    public static function fromActions(array $actions): self
    {
        return new self(
            status: isset($actions['status']) ? TicketStatus::from($actions['status']) : null,
            priority: isset($actions['priority']) ? TicketPriority::from($actions['priority']) : null,
            addTags: $actions['add_tags'] ?? [],
            removeTags: $actions['remove_tags'] ?? [],
            assignToMe: $actions['assign_to_me'] ?? false,
        );
    }
}

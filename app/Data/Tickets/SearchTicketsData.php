<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\SearchTickets;
use App\Data\InputData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Ticket list filters, as {@see SearchTickets} describes them. Saved views store these too.
 */
class SearchTicketsData extends InputData
{
    public const array SORTS = ['updated', 'created', 'due', 'priority'];

    /** Filters that take one value or a list. */
    public const array LIST_FILTERS = ['desk_id', 'status', 'priority', 'tag', 'assignee_id'];

    /** Every filter. */
    public const array FILTERS = [...self::LIST_FILTERS, 'search', 'custom_fields', 'requester_id', 'deleted', 'sort'];

    /**
     * @param  string|list<string>|Optional  $deskId
     * @param  string|list<string>|Optional  $status
     * @param  string|list<string>|Optional  $priority
     * @param  string|list<string>|Optional  $tag
     * @param  string|list<string>|Optional  $assigneeId
     * @param  array<string, string|list<string>>|Optional  $customFields  Select, multi-select and checkbox values keyed by custom field ID.
     */
    public function __construct(
        public string|Optional|null $search,
        public string|array|Optional $deskId,
        public string|array|Optional $status,
        public string|array|Optional $priority,
        public string|array|Optional $tag,
        public string|array|Optional $assigneeId,
        public array|Optional $customFields,
        public string|Optional $requesterId,
        public bool|Optional $deleted,
        public string|Optional $sort,
    ) {}

    /**
     * A list filter given one value is checked under its own key, so the error lands on
     * `status` rather than `status.0`.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $listRules = [
            'desk_id' => ['string'],
            'status' => [Rule::enum(TicketStatus::class)],
            'priority' => [Rule::enum(TicketPriority::class)],
            'tag' => ['string'],
            'assignee_id' => ['string'],
        ];

        $rules = [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_fields' => ['sometimes', 'array'],
            'requester_id' => ['sometimes', 'string'],
            'deleted' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(self::SORTS)],
        ];

        foreach ($listRules as $key => $itemRules) {
            if (is_array($context->payload[$key] ?? null)) {
                $rules[$key] = ['array'];
                $rules["{$key}.*"] = $itemRules;
            } else {
                $rules[$key] = ['sometimes', ...$itemRules];
            }
        }

        return $rules;
    }
}

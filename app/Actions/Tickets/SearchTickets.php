<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\SearchTicketsData;
use App\Enums\CustomFieldType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SearchTickets
{
    private const array FILTERABLE_FIELD_TYPES = [CustomFieldType::SELECT, CustomFieldType::MULTI_SELECT, CustomFieldType::CHECKBOX];

    /**
     * The tickets the actor can see, narrowed by any of:
     *
     * - `search`: `#123` or `123` matches the ticket number; other text matches the subject
     *   and comments (Postgres web-search syntax) or the requester's name or email.
     * - `desk_id`, `status`, `priority`, `tag` (a tag name): one value or a list.
     * - `assignee_id`: a user ID, `me` or `none`, or a list of them (any of).
     * - `custom_fields`: select, multi-select and checkbox values keyed by field ID. A field
     *   matches any of its values and the fields all have to match.
     * - `requester_id`: a contact ID.
     * - `deleted`: only deleted tickets.
     * - `sort`: `updated` (default, newest first), `created` (newest first), `due` (soonest
     *   first, undated last), `priority` (most urgent first), `status` (workflow order) or
     *   `number` (newest first) or `subject` (A to Z, ignoring case).
     * - `direction`: `asc` or `desc`, overriding the sort's default direction.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function handle(User $actor, array $filters): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Ticket::class);

        $fields = $this->customFields($filters);
        $validated = SearchTicketsData::validateAndCreateWith($filters, $this->customFieldRules($filters, $fields))->filled();

        foreach (SearchTicketsData::LIST_FILTERS as $key) {
            if (isset($validated[$key])) {
                $validated[$key] = Arr::wrap($validated[$key]);
            }
        }

        $search = trim((string) ($validated['search'] ?? ''));

        $query = Ticket::visibleTo($actor)
            ->when($search !== '', fn (Builder $query) => $this->search($query, $search))
            ->when($validated['desk_id'] ?? null, fn (Builder $query, array $deskIds) => $query->whereIn('desk_id', $deskIds))
            ->when($validated['status'] ?? null, fn (Builder $query, array $statuses) => $query->whereIn('status', $statuses))
            ->when($validated['priority'] ?? null, fn (Builder $query, array $priorities) => $query->whereIn('priority', $priorities))
            ->when($validated['tag'] ?? null, fn (Builder $query, array $tags) => $query->whereHas('tags', fn (Builder $query) => $query->whereIn(DB::raw('lower(name)'), array_map(mb_strtolower(...), $tags))))
            ->when($validated['assignee_id'] ?? null, fn (Builder $query, array $assignees) => $this->assignedTo($query, $actor, array_values($assignees)))
            ->when($validated['custom_fields'] ?? null, fn (Builder $query, array $values) => $this->customFieldValues($query, $fields, $values))
            ->when($validated['requester_id'] ?? null, fn (Builder $query, string $contactId) => $query->where('requester_id', $contactId))
            ->when((bool) ($validated['deleted'] ?? false), fn (Builder $query) => $query->onlyTrashed());

        return $this->sort($query, $validated['sort'] ?? 'updated', $validated['direction'] ?? null);
    }

    /**
     * The custom fields the filters mention, keyed by ID. Tenant-scoped, so another
     * workspace's ID is simply missing.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, CustomField>
     */
    private function customFields(array $filters): Collection
    {
        $ids = is_array($filters['custom_fields'] ?? null) ? array_keys($filters['custom_fields']) : [];

        return $ids === [] ? new Collection : CustomField::query()->whereKey($ids)->get()->keyBy('id');
    }

    /**
     * Only select, multi-select and checkbox fields can be filtered on, so any other ID,
     * including one that doesn't exist in this workspace, is rejected.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<string, CustomField>  $fields
     * @return array<string, array<int, mixed>>
     */
    private function customFieldRules(array $filters, Collection $fields): array
    {
        $rules = [];

        foreach ($filters['custom_fields'] ?? [] as $id => $value) {
            $type = $fields->get($id)?->type;

            if (! in_array($type, self::FILTERABLE_FIELD_TYPES, true)) {
                $rules["custom_fields.{$id}"] = ['prohibited'];

                continue;
            }

            $itemRules = $type === CustomFieldType::CHECKBOX ? ['boolean'] : ['string'];

            if (is_array($value)) {
                $rules["custom_fields.{$id}"] = ['array'];
                $rules["custom_fields.{$id}.*"] = $itemRules;
            } else {
                $rules["custom_fields.{$id}"] = $itemRules;
            }
        }

        return $rules;
    }

    /**
     * Each field matches any of its values (`@>` on the ticket's jsonb) and the fields are ANDed.
     *
     * @param  Builder<Ticket>  $query
     * @param  Collection<string, CustomField>  $fields
     * @param  array<string, string|list<string>>  $values
     */
    private function customFieldValues(Builder $query, Collection $fields, array $values): void
    {
        foreach ($values as $id => $value) {
            $type = $fields->get($id)->type;

            $query->where(function (Builder $query) use ($id, $type, $value): void {
                foreach (Arr::wrap($value) as $item) {
                    $query->orWhereRaw('custom_fields @> ?::jsonb', [json_encode([
                        $id => match ($type) {
                            CustomFieldType::MULTI_SELECT => [$item],
                            default => $type->cast($item),
                        },
                    ])]);
                }
            });
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  list<string>  $assignees  User IDs, `me` and `none`.
     */
    private function assignedTo(Builder $query, User $actor, array $assignees): void
    {
        $ids = array_map(fn (string $id): string => $id === 'me' ? $actor->getKey() : $id, array_diff($assignees, ['none']));

        $query->where(function (Builder $query) use ($assignees, $ids): void {
            if (in_array('none', $assignees, true)) {
                $query->whereDoesntHave('assignees');
            }

            if ($ids !== []) {
                $query->orWhereHas('assignees', fn (Builder $query) => $query->whereKey($ids));
            }
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function search(Builder $query, string $search): void
    {
        if (preg_match('/^#?(\d+)$/', $search, $matches)) {
            $query->where('number', (int) $matches[1]);

            return;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';

        // A UNION of id subqueries (trashed included; the deleted filter decides), so each branch can use its own index; an OR across EXISTS can't.
        $bySubject = Ticket::withTrashed()->select('id')->whereFullText('subject', $search, ['mode' => 'websearch']);
        $byComment = TicketComment::query()->select('ticket_id')->whereFullText('body', $search, ['mode' => 'websearch']);
        $byRequester = Ticket::withTrashed()->select('id')->whereIn('requester_id', Contact::query()->select('id')
            ->whereLike('name', $like)
            ->orWhereLike('email', $like));

        $query->whereIn('id', $bySubject->union($byComment)->union($byRequester));
    }

    /**
     * Sorts by the column in its default direction unless one is given. Ties fall back to
     * the newest update, then the ID.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function sort(Builder $query, string $sort, ?string $direction): Builder
    {
        $direction = ($direction ?? (in_array($sort, ['due', 'status', 'subject'], true) ? 'asc' : 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'created' => $query->orderBy('created_at', $direction),
            'due' => $query->orderByRaw("due_at {$direction} nulls last"),
            'priority' => $this->orderByCase($query, 'priority', TicketPriority::cases(), $direction),
            'status' => $this->orderByCase($query, 'status', TicketStatus::cases(), $direction),
            'number' => $query->orderBy('number', $direction),
            'subject' => $query->orderByRaw("lower(subject) {$direction}"),
            default => $query->orderBy('updated_at', $direction),
        };

        return $query->latest('updated_at')->orderByDesc('id');
    }

    /**
     * Orders a string-backed enum column by the enum's declaration order.
     *
     * @param  Builder<Ticket>  $query
     * @param  'priority'|'status'  $column
     * @param  list<BackedEnum>  $cases
     * @param  'asc'|'desc'  $direction
     */
    private function orderByCase(Builder $query, string $column, array $cases, string $direction): void
    {
        $values = array_column($cases, 'value');

        $query->orderByRaw('array_position(array['.implode(',', array_fill(0, count($values), '?')).']::text[], '.$column.') '.$direction, $values);
    }
}

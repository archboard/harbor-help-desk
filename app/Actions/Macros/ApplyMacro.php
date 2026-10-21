<?php

namespace App\Actions\Macros;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Actions\Tickets\SyncTicketTags;
use App\Actions\Tickets\UpdateTicket;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApplyMacro
{
    public function __construct(
        protected UpdateTicket $updateTicket,
        protected SyncTicketTags $syncTicketTags,
        protected SyncTicketAssignees $syncTicketAssignees,
        protected RenderMacroBody $renderMacroBody,
    ) {}

    /**
     * Run the macro's actions on the ticket through the ticket Actions, calling only those
     * that change something, and return the rendered body for the agent to edit and send.
     * Nothing is commented or emailed.
     *
     * @return array{body: ?string, is_internal: bool}
     */
    public function handle(User $actor, Ticket $ticket, Macro $macro): array
    {
        $gate = Gate::forUser($actor);
        $gate->authorize('view', $macro);
        $gate->authorize('update', $ticket);

        if ($macro->desk_id !== null && $macro->desk_id !== $ticket->desk_id) {
            throw new AuthorizationException(__('This macro belongs to another desk.'));
        }

        DB::transaction(function () use ($actor, $ticket, $macro): void {
            $this->applyFields($actor, $ticket, $macro->actions);
            $this->applyTags($actor, $ticket, $macro->actions);
            $this->applyAssignee($actor, $ticket, $macro->actions);
        });

        return [
            'body' => $this->renderMacroBody->handle($macro, $ticket, $actor),
            'is_internal' => $macro->body_is_internal,
        ];
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    protected function applyFields(User $actor, Ticket $ticket, array $actions): void
    {
        $changes = [];

        if (isset($actions['status']) && $actions['status'] !== $ticket->status->value) {
            $changes['status'] = $actions['status'];
        }

        if (isset($actions['priority']) && $actions['priority'] !== $ticket->priority->value) {
            $changes['priority'] = $actions['priority'];
        }

        if ($changes !== []) {
            $this->updateTicket->handle($actor, $ticket, $changes);
        }
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    protected function applyTags(User $actor, Ticket $ticket, array $actions): void
    {
        $lower = fn (array $names): array => array_map(mb_strtolower(...), $names);

        $current = $ticket->tags()->pluck('name')->all();
        $add = array_values(array_filter($actions['add_tags'] ?? [], fn (string $name): bool => ! in_array(mb_strtolower($name), $lower($current), true)));
        $remove = $lower($actions['remove_tags'] ?? []);
        $removing = array_filter($current, fn (string $name): bool => in_array(mb_strtolower($name), $remove, true));

        if ($add === [] && $removing === []) {
            return;
        }

        $kept = array_filter($current, fn (string $name): bool => ! in_array(mb_strtolower($name), $remove, true));

        $this->syncTicketTags->handle($actor, $ticket, ['names' => array_values([...$kept, ...$add])]);
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    protected function applyAssignee(User $actor, Ticket $ticket, array $actions): void
    {
        $current = $ticket->assignees()->pluck('users.id')->all();

        if (($actions['assign_to_me'] ?? false) && ! in_array($actor->id, $current, true)) {
            $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => [...$current, $actor->id]]);
        }
    }
}

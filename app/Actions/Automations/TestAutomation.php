<?php

namespace App\Actions\Automations;

use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Automations\EditorScope;
use App\Automations\Engine;
use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\AutomationTestResultData;
use App\Data\Automations\TestAutomationData;
use App\Models\Automation;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class TestAutomation
{
    /** Triggers that start from something other than a ticket event. */
    protected const array NOT_EVENT_BASED = ['schedule', 'webhook'];

    public function __construct(protected Engine $engine, protected AutomationRegistry $registry) {}

    /**
     * Dry-run an unsaved definition against a ticket: report whether the trigger matches the chosen
     * event, evaluate the conditions and paths against the ticket as it is now, and describe what
     * each action would do. Writes nothing (see {@see Engine::dryRun()}).
     *
     * The actor needs the rights to save an automation with that desk scope and to see the ticket,
     * which must be on that desk for a desk-scoped test.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input): AutomationTestResultData
    {
        Gate::forUser($actor)->authorize('viewAny', Automation::class);
        app(EditorScope::class)->set($actor, $input['desk_id'] ?? null);

        $data = TestAutomationData::validateAndCreateWith($input, [
            'ticket_event_id' => [function (string $attribute, mixed $value, Closure $fail) use ($input): void {
                if ($value !== null && ! TicketEvent::query()->whereKey($value)->where('ticket_id', $input['ticket_id'] ?? null)->exists()) {
                    $fail(__('This event does not belong to the ticket.'));
                }
            }],
        ]);

        $deskId = $data->deskId instanceof Optional ? null : $data->deskId;
        Gate::forUser($actor)->authorize('create', new Automation(['desk_id' => $deskId]));

        $ticket = Ticket::query()->findOrFail($data->ticketId);
        Gate::forUser($actor)->authorize('view', $ticket);

        if ($deskId !== null && $ticket->desk_id !== $deskId) {
            throw ValidationException::withMessages(['ticket_id' => __('The ticket is not on the automation’s desk.')]);
        }

        $definition = AutomationDefinitionData::from($data->definition);
        $event = $data->ticketEventId instanceof Optional || $data->ticketEventId === null ? null : TicketEvent::query()->findOrFail($data->ticketEventId);
        $payload = $data->samplePayload instanceof Optional ? [] : ($data->samplePayload ?? []);

        [$triggerMatched, $triggerNote] = $this->trigger($definition, $event);

        $result = $this->engine->dryRun($definition, Context::forDryRun($ticket, $event, $payload, $definition, __('Test run')));

        return new AutomationTestResultData($triggerMatched, $triggerNote, $result['matched'], $result['stopped'], $result['log'], $result['error']);
    }

    /**
     * @return array{0: bool|null, 1: string|null}
     */
    protected function trigger(AutomationDefinitionData $definition, ?TicketEvent $event): array
    {
        if (in_array($definition->trigger->type, self::NOT_EVENT_BASED, true)) {
            return [null, __('This trigger does not start from a ticket event, so it was not evaluated.')];
        }

        if ($event === null) {
            return [null, __('No event was chosen, so the trigger was not evaluated.')];
        }

        return [$this->registry->trigger($definition->trigger->type)?->matches($event, $definition->trigger->config) === true, null];
    }
}

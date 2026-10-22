<?php

use App\Automations\Context;
use App\Automations\Triggers\CommentAdded;
use App\Automations\Triggers\FieldChanged;
use App\Automations\Triggers\SlaEvent;
use App\Automations\Triggers\TicketCreated;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->ticket = Ticket::factory()->create(['source' => TicketSource::EMAIL]);
});

test('ticket.created matches created events of the configured sources', function (array $config, bool $matches) {
    $event = $this->ticket->recordEvent(TicketEventType::CREATED, null);

    expect((new TicketCreated)->matches($event, $config))->toBe($matches);
})->with([
    'any source' => [[], true],
    'its source' => [['sources' => ['email', 'web']], true],
    'another source' => [['sources' => ['api']], false],
]);

test('ticket.created ignores other events', function () {
    $event = $this->ticket->recordEvent(TicketEventType::RESTORED, null);

    expect((new TicketCreated)->matches($event, []))->toBeFalse();
});

test('ticket.comment_added filters on who wrote the message and its visibility', function () {
    $event = fn (TicketEventType $type, User|Contact|Automation|null $actor) => $this->ticket->recordEvent($type, $actor, ['comment_id' => 'cmt_x']);
    $trigger = new CommentAdded;
    $contact = Contact::factory()->create();
    $agent = User::factory()->create();
    $automation = Automation::factory()->create();

    $requesterReply = $event(TicketEventType::COMMENT_ADDED, $contact);
    $agentReply = $event(TicketEventType::COMMENT_ADDED, $agent);
    $agentNote = $event(TicketEventType::INTERNAL_NOTE_ADDED, $agent);
    $automationReply = $event(TicketEventType::COMMENT_ADDED, $automation);

    expect($trigger->matches($requesterReply, ['author' => 'requester']))->toBeTrue()
        ->and($trigger->matches($agentReply, ['author' => 'requester']))->toBeFalse()
        ->and($trigger->matches($agentReply, ['author' => 'agent']))->toBeTrue()
        ->and($trigger->matches($requesterReply, ['author' => 'agent']))->toBeFalse()
        ->and($trigger->matches($agentNote, ['author' => 'agent', 'visibility' => 'internal']))->toBeTrue()
        ->and($trigger->matches($agentNote, ['visibility' => 'public']))->toBeFalse()
        ->and($trigger->matches($agentReply, ['visibility' => 'public']))->toBeTrue()
        ->and($trigger->matches($agentNote, []))->toBeTrue()
        // Neither a requester nor an agent: only `any` matches a message an automation wrote.
        ->and($trigger->matches($automationReply, ['author' => 'agent']))->toBeFalse()
        ->and($trigger->matches($automationReply, ['author' => 'requester']))->toBeFalse()
        ->and($trigger->matches($automationReply, ['author' => 'any']))->toBeTrue()
        ->and($trigger->matches($this->ticket->recordEvent(TicketEventType::COMMENT_EDITED, $agent), []))->toBeFalse();
});

test('ticket.comment_added fills the message placeholders', function () {
    $agent = User::factory()->create(['name' => 'Jane Doe']);
    $comment = TicketComment::factory()->for($this->ticket)->create(['author_id' => $agent->id, 'body' => 'Where is my order?']);
    $event = $this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, $agent, ['comment_id' => $comment->id]);

    expect((new CommentAdded)->placeholderValues(new Context($this->ticket, $event)))
        ->toBe(['message.body' => 'Where is my order?', 'message.author_name' => 'Jane Doe'])
        ->and(array_keys((new CommentAdded)->placeholders()))->toBe(['message.body', 'message.author_name']);
});

test('ticket.field_changed matches the listed fields and exposes the change', function () {
    $event = $this->ticket->recordEvent(TicketEventType::PRIORITY_CHANGED, null, ['from' => 'normal', 'to' => 'urgent']);
    $tag = $this->ticket->recordEvent(TicketEventType::TAG_ADDED, null, ['name' => 'vip']);
    $trigger = new FieldChanged;

    expect($trigger->matches($event, []))->toBeTrue()
        ->and($trigger->matches($event, ['fields' => ['ticket.status', 'ticket.priority']]))->toBeTrue()
        ->and($trigger->matches($event, ['fields' => ['ticket.status']]))->toBeFalse()
        ->and($trigger->matches($tag, ['fields' => ['ticket.tags']]))->toBeTrue()
        ->and($trigger->matches($this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, null), []))->toBeFalse()
        ->and($trigger->placeholderValues(new Context($this->ticket, $event)))
        ->toBe(['event.field' => 'ticket.priority', 'event.from' => 'normal', 'event.to' => 'urgent'])
        ->and($trigger->placeholderValues(new Context($this->ticket, $tag)))
        ->toBe(['event.field' => 'ticket.tags', 'event.from' => '', 'event.to' => 'vip']);
});

test('ticket.sla matches the kind and the metrics', function () {
    $properties = ['metric' => 'first_response', 'due_at' => now()->toIso8601String()];
    $warning = $this->ticket->recordEvent(TicketEventType::SLA_WARNING, null, $properties);
    $breached = $this->ticket->recordEvent(TicketEventType::SLA_BREACHED, null, ['metric' => 'resolution'] + $properties);
    $trigger = new SlaEvent;

    expect($trigger->matches($warning, []))->toBeTrue()
        ->and($trigger->matches($breached, []))->toBeTrue()
        ->and($trigger->matches($warning, ['kind' => 'breached']))->toBeFalse()
        ->and($trigger->matches($breached, ['kind' => 'breached', 'metrics' => ['resolution']]))->toBeTrue()
        ->and($trigger->matches($breached, ['metrics' => ['first_response']]))->toBeFalse()
        ->and($trigger->matches($this->ticket->recordEvent(TicketEventType::STATUS_CHANGED, null, ['from' => 'open', 'to' => 'closed']), []))->toBeFalse();
});

test('the trigger configs reject unknown values', function () {
    $errors = fn (object $trigger, array $config) => validator($config, $trigger->configRules())->errors()->keys();

    expect($errors(new TicketCreated, ['sources' => ['carrier_pigeon']]))->toBe(['sources.0'])
        ->and($errors(new CommentAdded, ['author' => 'robot']))->toBe(['author'])
        ->and($errors(new FieldChanged, ['fields' => ['ticket.status', 'ticket.nonsense']]))->toBe(['fields.1'])
        ->and($errors(new FieldChanged, ['fields' => ['custom_field.cfd_x']]))->toBe([])
        ->and($errors(new SlaEvent, ['kind' => 'soon', 'metrics' => ['everything']]))->toBe(['kind', 'metrics.0']);
});

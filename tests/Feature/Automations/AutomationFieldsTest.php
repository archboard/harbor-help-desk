<?php

use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Automations\Fields\CoreFields;
use App\Automations\FieldType;
use App\Enums\CustomFieldType;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketActivityRecorded;
use App\Models\Attachment;
use App\Models\BusinessSchedule;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketSlaTarget;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * A ticket worked through a Wednesday morning, with "now" at 12:00. Returns the context of
 * the requester's comment, which has one attachment.
 */
function morningContext(): Context
{
    Event::fake([TicketActivityRecorded::class]);

    $requester = Contact::factory()->create(['name' => 'Jane Smith', 'email' => 'Jane@Acme.test']);
    $agent = User::factory()->create();
    $field = CustomField::factory()->select()->create(['name' => 'Plan']);

    test()->travelTo('2026-10-21 09:00');
    $ticket = Ticket::factory()->create([
        'requester_id' => $requester->id,
        'subject' => 'Order broke',
        'priority' => TicketPriority::HIGH,
        'source' => TicketSource::EMAIL,
        'due_at' => '2026-10-21 14:00',
        'custom_fields' => [$field->id => 'Large'],
    ]);
    $ticket->tags()->attach(Tag::factory()->create(['name' => 'vip']), ['workspace_id' => $ticket->workspace_id]);
    $ticket->assignees()->attach($agent, ['workspace_id' => $ticket->workspace_id]);
    TicketSlaTarget::create(['ticket_id' => $ticket->id, 'metric' => SlaMetric::FIRST_RESPONSE, 'target_minutes' => 210, 'started_at' => now(), 'due_at' => '2026-10-21 12:30']);

    test()->travelTo('2026-10-21 09:30');
    $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'author_type' => 'contact', 'author_id' => $requester->id, 'body' => 'Please refund me']);
    Attachment::factory()->create(['ticket_id' => $ticket->id, 'ticket_comment_id' => $comment->id, 'filename' => 'invoice.pdf']);
    $event = $ticket->recordEvent(TicketEventType::COMMENT_ADDED, $requester, ['comment_id' => $comment->id]);

    test()->travelTo('2026-10-21 10:30');
    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'author_type' => 'user', 'author_id' => $agent->id]);
    TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'author_type' => 'user', 'author_id' => $agent->id]);

    test()->travelTo('2026-10-21 11:00');
    $ticket->update(['status' => TicketStatus::PENDING]);
    $ticket->recordEvent(TicketEventType::STATUS_CHANGED, $agent, ['from' => 'open', 'to' => 'pending']);

    test()->travelTo('2026-10-21 12:00');
    test()->field = $field;
    test()->agent = $agent;

    return new Context($ticket, $event);
}

it('resolves each core field from the context', function (string $key, mixed $expected) {
    $context = morningContext();
    $expected = $expected instanceof Closure ? $expected($context, test()) : $expected;

    expect(app(AutomationRegistry::class)->field($key)->resolve($context))->toEqual($expected);
})->with([
    'ticket.number' => ['ticket.number', fn (Context $c) => $c->ticket->number],
    'ticket.subject' => ['ticket.subject', 'Order broke'],
    'ticket.status' => ['ticket.status', 'pending'],
    'ticket.priority' => ['ticket.priority', 'high'],
    'ticket.desk' => ['ticket.desk', fn (Context $c) => $c->ticket->desk_id],
    'ticket.assignees' => ['ticket.assignees', fn (Context $c, $test) => [$test->agent->id]],
    'ticket.tags' => ['ticket.tags', ['vip']],
    'ticket.due_at' => ['ticket.due_at', fn () => now()->setTime(14, 0)],
    'ticket.source' => ['ticket.source', 'email'],
    'ticket.mailbox' => ['ticket.mailbox', null],
    'ticket.sla_policy' => ['ticket.sla_policy', null],
    'requester.name' => ['requester.name', 'Jane Smith'],
    'requester.email' => ['requester.email', 'Jane@Acme.test'],
    'requester.email_domain' => ['requester.email_domain', 'acme.test'],
    'message.body' => ['message.body', 'Please refund me'],
    'message.author_type' => ['message.author_type', 'requester'],
    'message.is_internal' => ['message.is_internal', false],
    'message.has_attachments' => ['message.has_attachments', true],
    'message.attachment_filenames' => ['message.attachment_filenames', 'invoice.pdf'],
    'event.field' => ['event.field', null],
    'event.from' => ['event.from', null],
    'event.to' => ['event.to', null],
    'event.actor_type' => ['event.actor_type', 'requester'],
    'event.actor' => ['event.actor', null],
    'time.since_created' => ['time.since_created', 180],
    'time.since_updated' => ['time.since_updated', 60],
    'time.since_requester_reply' => ['time.since_requester_reply', 150],
    'time.since_agent_reply' => ['time.since_agent_reply', 90],
    'time.since_status_changed' => ['time.since_status_changed', 60],
    'time.until_due' => ['time.until_due', 120],
    'time.until_sla_due' => ['time.until_sla_due', 30],
    'sla.first_response' => ['sla.first_response', 'active'],
    'sla.resolution' => ['sla.resolution', 'none'],
]);

it('resolves a custom field value from the ticket', function () {
    $context = morningContext();

    expect(app(AutomationRegistry::class)->field('custom_field.'.test()->field->id)->resolve($context))->toBe('Large');
});

it('is covered by the dataset above', function () {
    $source = file_get_contents(__FILE__);

    foreach (CoreFields::all() as $field) {
        expect($source)->toContain("'{$field->key()}'");
    }
});

it('reads the change a change event made', function () {
    $context = morningContext();
    $event = $context->ticket->events()->where('type', TicketEventType::STATUS_CHANGED)->sole();
    $statusContext = new Context($context->ticket, $event);
    $registry = app(AutomationRegistry::class);

    expect($registry->field('event.field')->resolve($statusContext))->toBe('ticket.status')
        ->and($registry->field('event.from')->resolve($statusContext))->toBe('open')
        ->and($registry->field('event.to')->resolve($statusContext))->toBe('pending')
        ->and($registry->field('event.actor_type')->resolve($statusContext))->toBe('agent')
        ->and($registry->field('event.actor')->resolve($statusContext))->toBe(test()->agent->id)
        ->and($registry->field('message.body')->resolve($statusContext))->toBeNull();
});

it('reads the sla states from the ticket targets', function (array $attributes, string $state) {
    $context = morningContext();
    $context->ticket->slaTargets()->update($attributes);

    expect(app(AutomationRegistry::class)->field('sla.first_response')->resolve(new Context($context->ticket)))->toBe($state);
})->with([
    'paused' => [['paused_at' => '2026-10-21 10:00'], 'paused'],
    'met' => [['achieved_at' => '2026-10-21 10:00'], 'met'],
    'breached' => [['breached_at' => '2026-10-21 10:00'], 'breached'],
    'breached after being met' => [['achieved_at' => '2026-10-21 11:00', 'breached_at' => '2026-10-21 10:00'], 'breached'],
]);

it('counts minutes in the schedule\'s business time', function () {
    $context = morningContext();
    $schedule = BusinessSchedule::factory()->create();
    $context->ticket->forceFill(['created_at' => '2026-10-20 16:00'])->saveQuietly();

    expect(app(AutomationRegistry::class)->field('time.since_created')->resolve(new Context($context->ticket), $schedule))->toBe(60 + 180);
});

it('turns custom fields into typed fields for the current workspace only', function (CustomFieldType $type, FieldType $expected) {
    $field = CustomField::factory()->create(['type' => $type, 'options' => $type->hasOptions() ? ['A', 'B'] : null]);

    $found = app(AutomationRegistry::class)->field("custom_field.$field->id");

    expect($found->type())->toBe($expected)->and($found->label())->toBe($field->name);

    Workspace::factory()->create()->makeCurrent();

    expect(app(AutomationRegistry::class)->field("custom_field.$field->id"))->toBeNull()
        ->and(app(AutomationRegistry::class)->fields())->not->toHaveKey("custom_field.$field->id");
})->with([
    [CustomFieldType::TEXT, FieldType::STRING],
    [CustomFieldType::TEXTAREA, FieldType::TEXT],
    [CustomFieldType::NUMBER, FieldType::NUMBER],
    [CustomFieldType::DECIMAL, FieldType::NUMBER],
    [CustomFieldType::DATE, FieldType::DATETIME],
    [CustomFieldType::CHECKBOX, FieldType::BOOLEAN],
    [CustomFieldType::SELECT, FieldType::ENUM],
    [CustomFieldType::MULTI_SELECT, FieldType::MULTI_ENUM],
]);

it('reads webhook payload values by path with their type', function (string $key, mixed $expected) {
    $context = new Context(Ticket::factory()->create(), payload: ['order' => ['id' => 1042, 'paid' => 'true', 'customer' => 'Jane']]);

    expect(app(AutomationRegistry::class)->field($key)->resolve($context))->toBe($expected);
})->with([
    ['webhook.payload.order.customer', 'Jane'],
    ['webhook.payload.order.id', '1042'],
    ['webhook.payload_number.order.id', 1042],
    ['webhook.payload_boolean.order.paid', true],
    ['webhook.payload.order.missing', null],
]);

it('exposes enum options for the catalog', function () {
    Desk::factory()->create(['name' => 'Billing']);

    $registry = app(AutomationRegistry::class);

    expect($registry->field('ticket.status')->options())->toHaveKey('on_hold', 'On hold')
        ->and($registry->field('ticket.desk')->options())->toContain('Billing')
        ->and($registry->field('sla.resolution')->options())->toHaveKeys(['active', 'paused', 'met', 'breached', 'none']);
});

test('reply and status times resolve from the newest row, not the oldest', function () {
    Event::fake([TicketActivityRecorded::class]);
    $this->travelTo('2026-10-21 08:00');
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create();
    $reply = function (Model $author, string $body) use ($ticket): void {
        $comment = new TicketComment(['body' => $body, 'is_internal' => false]);
        $comment->author()->associate($author);
        $ticket->comments()->save($comment);
    };

    $reply($ticket->requester, 'first');
    $reply($agent, 'first');
    $ticket->recordEvent(TicketEventType::STATUS_CHANGED, null, ['from' => 'open', 'to' => 'pending']);
    $this->travelTo('2026-10-21 10:00');
    $reply($ticket->requester, 'second');
    $reply($agent, 'second');
    $ticket->recordEvent(TicketEventType::STATUS_CHANGED, null, ['from' => 'pending', 'to' => 'open']);
    $this->travelTo('2026-10-21 10:30');

    $context = new Context($ticket);
    $registry = app(AutomationRegistry::class);

    expect($registry->field('time.since_requester_reply')->resolve($context))->toBe(30)
        ->and($registry->field('time.since_agent_reply')->resolve($context))->toBe(30)
        ->and($registry->field('time.since_status_changed')->resolve($context))->toBe(30);
});

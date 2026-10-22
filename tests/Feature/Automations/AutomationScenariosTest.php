<?php

use App\Actions\Tickets\CheckSlaTargets;
use App\Enums\DeskRole;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketSlaTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationNotice;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    actAs($this->admin, $this->workspace);
});

/**
 * Creates and enables an automation through the API, so the definition is validated for real:
 * one condition on a field, then the actions.
 *
 * @param  array<string, mixed>  $trigger
 * @param  list<array{string, array<string, mixed>}>  $actions
 */
function scenario(string $name, array $trigger, string $field, string $operator, mixed $value, array $actions): void
{
    $steps = [['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => $field, 'operator' => $operator, 'value' => $value]]]];

    foreach ($actions as $index => [$action, $config]) {
        $steps[] = ['id' => "a$index", 'type' => 'action', 'action' => $action, 'config' => $config];
    }

    $base = 'http://acme.harbor.test/api/v1/automations';
    $id = test()->postJson($base, ['name' => $name, 'definition' => ['trigger' => $trigger, 'steps' => $steps]])->assertCreated()->json('id');
    test()->putJson("$base/$id/enabled", ['enabled' => true])->assertOk();
}

test('route by keyword: a new refund ticket moves to the billing desk', function () {
    $billing = Desk::factory()->create(['name' => 'Billing']);
    scenario('Route refunds', ['type' => 'ticket.created', 'config' => []], 'ticket.subject', 'contains', 'refund', [['move_desk', ['desk_id' => $billing->id]]]);
    $refund = Ticket::factory()->create(['subject' => 'I want a refund']);
    $other = Ticket::factory()->create(['subject' => 'Printer jam']);
    $originalDeskId = $other->desk_id;

    $refund->recordEvent(TicketEventType::CREATED, null);
    $other->recordEvent(TicketEventType::CREATED, null);

    $moved = $refund->events()->where('type', TicketEventType::DESK_CHANGED)->sole();
    expect($refund->refresh()->desk_id)->toBe($billing->id)
        ->and($moved->actorName())->toBe('Route refunds')
        ->and($other->refresh()->desk_id)->toBe($originalDeskId);
});

test('tag by requester domain: acme.com requesters get the vip tag', function () {
    scenario('VIP domains', ['type' => 'ticket.created', 'config' => []], 'requester.email_domain', 'is', 'acme.com', [['tags', ['mode' => 'add', 'names' => ['vip']]]]);
    $vip = Ticket::factory()->for(Contact::factory()->create(['email' => 'Dana@Acme.com']), 'requester')->create();
    $regular = Ticket::factory()->for(Contact::factory()->create(['email' => 'sam@example.com']), 'requester')->create();

    $vip->recordEvent(TicketEventType::CREATED, null);
    $regular->recordEvent(TicketEventType::CREATED, null);

    expect($vip->tags()->pluck('name')->all())->toBe(['vip'])->and($regular->tags()->count())->toBe(0);
});

test('escalate on SLA breach: the priority goes urgent and the desk leads are notified', function () {
    Notification::fake();
    $this->travelTo(now()->startOfMinute());
    $desk = Desk::sole();
    $lead = deskMember($this->workspace, $desk, DeskRole::LEAD);
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create(['priority' => TicketPriority::NORMAL]);
    TicketSlaTarget::create([
        'ticket_id' => $ticket->id, 'metric' => SlaMetric::FIRST_RESPONSE, 'target_minutes' => 60,
        'started_at' => now(), 'warn_at' => now()->addMinutes(45), 'due_at' => now()->addMinutes(60),
    ]);
    scenario('Escalate', ['type' => 'ticket.sla', 'config' => ['kind' => 'breached']], 'sla.first_response', 'is', 'breached', [
        ['set_field', ['field' => 'ticket.priority', 'value' => 'urgent']],
        ['notify', ['recipients' => 'desk_leads', 'subject' => 'Breach on #{{ ticket.number }}', 'body' => '{{ ticket.subject }} is now urgent.']],
    ]);

    $this->travel(46)->minutes();
    app(CheckSlaTargets::class)->handle();
    expect($ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);

    $this->travel(15)->minutes();
    app(CheckSlaTargets::class)->handle();

    expect($ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
    Notification::assertSentTo($lead, AutomationNotice::class, fn (AutomationNotice $n) => $n->title === "Breach on #{$ticket->number}" && $n->body === "{$ticket->subject} is now urgent.");
    Notification::assertNotSentTo($agent, AutomationNotice::class);
});

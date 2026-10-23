<?php

use App\Actions\Tickets\CheckSlaTargets;
use App\Automations\DefinitionValidator;
use App\Automations\EditorScope;
use App\Automations\Templates;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketSlaTarget;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->base = 'http://acme.harbor.test/api/v1/automations';
});

dataset('templates', Templates::KEYS);

test('every template validates against the registry', function (string $key) {
    app(EditorScope::class)->set($this->admin, null);

    expect(app(DefinitionValidator::class)->errors(Templates::definitionFor($key, null)))->toBe([]);
})->with('templates');

test('creating from a template stores its trigger and steps, disabled', function (string $key, string $trigger, int $steps) {
    actAs($this->admin, $this->workspace);

    $id = $this->postJson($this->base, ['name' => 'From template', 'template' => $key, 'enabled' => true])
        ->assertCreated()->assertJsonPath('enabled', false)->json('id');

    $stored = Automation::findOrFail($id);
    expect($stored->enabled)->toBeFalse()
        ->and($stored->trigger_type)->toBe($trigger)
        ->and($stored->definition->steps)->toHaveCount($steps);
})->with([
    ['auto_close_stale_pending', 'schedule', 2],
    ['tag_by_keyword', 'ticket.comment_added', 2],
    ['route_by_requester_domain', 'ticket.created', 2],
    ['escalate_on_sla_breach', 'ticket.sla', 3],
    ['notify_leads_of_urgent', 'ticket.field_changed', 2],
    ['acknowledge_new_email', 'ticket.created', 2],
    ['forward_urgent_to_webhook', 'ticket.field_changed', 2],
]);

test('creating from an unknown template returns 422 with a template error', function () {
    actAs($this->admin, $this->workspace);

    $this->postJson($this->base, ['name' => 'Nope', 'template' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors(['template']);
});

test('the templates endpoint lists all templates for admins and returns 403 for agents, on web and API', function () {
    $this->actingAs($this->admin)->getJson('http://acme.harbor.test/automations/templates')
        ->assertOk()->assertJsonCount(7)->assertJsonPath('0.key', 'auto_close_stale_pending')->assertJsonPath('0.category', 'Housekeeping')->assertJsonPath('0.trigger_type', 'schedule')
        ->assertJsonPath('0.definition.steps.0.id', 'condition');

    actAs($this->admin, $this->workspace);
    $this->getJson("$this->base/templates")->assertOk()->assertJsonCount(7)->assertJsonPath('6.name', 'Forward urgent tickets to a webhook');

    $agent = deskMember($this->workspace, $this->desk);
    $this->actingAs($agent)->getJson('http://acme.harbor.test/automations/templates')->assertForbidden();
    actAs($agent, $this->workspace);
    $this->getJson("$this->base/templates")->assertForbidden();
});

test('a tag-by-keyword automation tags the ticket on a matching requester message once enabled', function () {
    actAs($this->admin, $this->workspace);
    $id = $this->postJson($this->base, ['name' => 'Tag', 'template' => 'tag_by_keyword'])->assertCreated()->json('id');
    $this->putJson("$this->base/$id/enabled", ['enabled' => true])->assertOk();
    $ticket = Ticket::factory()->create();
    $hit = new TicketComment(['body' => 'Please refund me', 'is_internal' => false]);
    $hit->author()->associate($ticket->requester);
    $ticket->comments()->save($hit);

    $ticket->recordEvent(TicketEventType::COMMENT_ADDED, $ticket->requester, ['comment_id' => $hit->id]);

    expect($ticket->tags()->pluck('name')->all())->toBe(['refund']);
});

test('an escalate-on-breach automation makes the ticket urgent once an SLA is breached and it is enabled', function () {
    $this->travelTo(now()->startOfMinute());
    actAs($this->admin, $this->workspace);
    $id = $this->postJson($this->base, ['name' => 'Escalate', 'template' => 'escalate_on_sla_breach'])->assertCreated()->json('id');
    $this->putJson("$this->base/$id/enabled", ['enabled' => true])->assertOk();
    $ticket = Ticket::factory()->for($this->desk)->create(['priority' => TicketPriority::NORMAL]);
    TicketSlaTarget::create([
        'ticket_id' => $ticket->id, 'metric' => SlaMetric::FIRST_RESPONSE, 'target_minutes' => 60,
        'started_at' => now(), 'warn_at' => now()->addMinutes(45), 'due_at' => now()->addMinutes(60),
    ]);

    $this->travel(61)->minutes();
    app(CheckSlaTargets::class)->handle();

    expect($ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});

test('route-by-domain uses the scope desk or the workspace\'s own first desk, and a foreign desk returns 422', function () {
    actAs($this->admin, $this->workspace);
    $billing = Desk::factory()->create();
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $deskB = $other->execute(fn () => Desk::sole());

    $first = $this->postJson($this->base, ['name' => 'Domain', 'template' => 'route_by_requester_domain'])->assertCreated()->json('id');
    $scoped = $this->postJson($this->base, ['name' => 'Scoped', 'template' => 'route_by_requester_domain', 'desk_id' => $billing->id])->assertCreated()->json('id');

    $moveDesk = fn (string $id) => Automation::findOrFail($id)->definition->steps[1]->config['desk_id'];
    expect($moveDesk($first))->toBe(Desk::query()->orderBy('id')->value('id'))->and($moveDesk($scoped))->toBe($billing->id);

    $this->postJson($this->base, ['name' => 'Leak', 'template' => 'route_by_requester_domain', 'desk_id' => $deskB->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['desk_id']);

    expect(Automation::query()->count())->toBe(2)
        ->and($other->execute(fn () => Automation::query()->count()))->toBe(0);
});

test('listed templates reference no workspace\'s desks', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $deskB = $other->execute(fn () => Desk::sole());
    $json = json_encode(Templates::all());

    expect($json)->not->toContain($this->desk->id)->not->toContain($deskB->id);
});

test('creating from a template on a workspace the token does not cover returns 403 and creates nothing', function () {
    $outsider = User::factory()->create();
    Workspace::factory()->create(['subdomain' => 'other'])->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);
    actAs($outsider, Workspace::query()->where('subdomain', 'other')->first());

    $this->postJson($this->base, ['name' => 'X', 'template' => 'tag_by_keyword'])->assertForbidden();
    expect(Automation::query()->count())->toBe(0);
});

test('the templates endpoint returns 401 without a token', function () {
    $this->getJson("$this->base/templates")->assertUnauthorized();
});

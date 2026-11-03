<?php

use App\Actions\ApiTokens\CreatePersonalAccessToken;
use App\Actions\ApiTokens\RevokePersonalAccessToken;
use App\Actions\CustomFields\CreateCustomField;
use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Macros\CreateMacro;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Actions\Workspaces\UpdateWorkspaceSettings;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();

    $this->admin = User::factory()->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->otherAdmin = User::factory()->create();
    $this->other->users()->attach($this->otherAdmin, ['role' => WorkspaceRole::ADMIN]);
    $this->other->execute(function () {
        AuditEvent::record(AuditAction::DESK_CREATED, $this->otherAdmin);
        $ticket = Ticket::factory()->create(['desk_id' => Desk::sole()->id]);
        $ticket->recordEvent(TicketEventType::COMMENT_ADDED, $this->otherAdmin);
    });
    $this->workspace->makeCurrent();

    $this->web = 'http://acme.harbor.test/workspace/audit-log';
    $this->api = 'http://acme.harbor.test/api/v1';
});

test('admin Actions record an event with the actor, subject and no PII', function () {
    $this->actingAs($this->admin);

    (new UpdateWorkspace)->handle($this->admin, $this->workspace, ['name' => 'Secret Corp Name']);
    (new UpdateWorkspaceSettings)->handle($this->admin, $this->workspace, ['surveys_enabled' => true]);
    $desk = (new CreateDesk)->handle($this->admin, ['name' => 'Billing Desk']);
    $field = (new CreateCustomField)->handle($this->admin, ['name' => 'Order number', 'type' => 'text']);

    $events = AuditEvent::query()->oldest('id')->get();

    expect($events->pluck('action')->all())->toBe([
        AuditAction::WORKSPACE_UPDATED,
        AuditAction::WORKSPACE_SETTINGS_UPDATED,
        AuditAction::DESK_CREATED,
        AuditAction::CUSTOM_FIELD_CREATED,
    ])
        ->and($events[0]->properties)->toBe(['changed' => ['name']])
        ->and($events[1]->properties)->toBe(['changed' => ['surveys_enabled']])
        ->and($events[2]->subject_type)->toBe('desk')->and($events[2]->subject_id)->toBe($desk->id)
        ->and($events[3]->subject_id)->toBe($field->id)
        ->and($events->every(fn (AuditEvent $event) => $event->actor_id === $this->admin->id && $event->workspace_id === $this->workspace->id))->toBeTrue()
        ->and(json_encode($events->pluck('properties')))->not->toContain('Secret Corp')->not->toContain('Billing')->not->toContain('ada@example.com');
});

test('archiving a desk is recorded', function () {
    $this->actingAs($this->admin);
    $desk = (new CreateDesk)->handle($this->admin, ['name' => 'Extra']);

    (new ArchiveDesk)->handle($this->admin, $desk);

    expect(AuditEvent::query()->where('action', AuditAction::DESK_ARCHIVED)->sole()->subject_id)->toBe($desk->id);
});

test('only shared macros are recorded', function () {
    (new CreateMacro)->handle($this->admin, ['name' => 'Mine', 'scope' => 'personal', 'body' => 'Hi']);
    (new CreateMacro)->handle($this->admin, ['name' => 'Everyone', 'scope' => 'workspace', 'body' => 'Hi']);

    expect(AuditEvent::query()->where('action', AuditAction::MACRO_CREATED)->count())->toBe(1);
});

test('API tokens are recorded once per workspace without the token', function () {
    $this->admin->workspaces()->syncWithoutDetaching([$this->other->id => ['role' => WorkspaceRole::ADMIN]]);

    $result = (new CreatePersonalAccessToken)->handle($this->admin, ['name' => 'CI', 'workspaces' => [$this->workspace->id, $this->other->id]]);
    $tokenId = $result->getToken()->getKey();

    expect(AuditEvent::query()->where('action', AuditAction::API_TOKEN_CREATED)->sole()->properties)->toBe(['token_id' => $tokenId])
        ->and(AuditEvent::withoutGlobalScopes()->where('action', AuditAction::API_TOKEN_CREATED)->count())->toBe(2)
        ->and(json_encode(AuditEvent::withoutGlobalScopes()->get()))->not->toContain($result->accessToken);

    (new RevokePersonalAccessToken)->handle($this->admin, $tokenId);

    expect(AuditEvent::withoutGlobalScopes()->where('action', AuditAction::API_TOKEN_REVOKED)->count())->toBe(2);
});

test('admins read the admin activity on the web and filter it', function () {
    $this->actingAs($this->admin);
    (new CreateDesk)->handle($this->admin, ['name' => 'Extra']);
    (new UpdateWorkspace)->handle($this->admin, $this->workspace, ['name' => 'Acme 2']);

    $this->get($this->web)->assertInertia(fn (Assert $page) => $page
        ->component('workspace/AuditLog')
        ->where('tab', 'admin')
        ->has('events.data', 2)
        ->where('events.data.0.action', 'workspace_updated')
        ->where('events.data.0.actor_name', 'Ada Admin'));

    $this->get("$this->web?action=desk_created")->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
    $this->get("$this->web?actor_id={$this->agent->id}")->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
    $this->get("$this->web?from=2999-01-01")->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
    $this->get("$this->web?action=nope")->assertSessionHasErrors('action');
});

test('the ticket activity tab lists the workspace\'s ticket events', function () {
    $ticket = Ticket::factory()->create(['desk_id' => $this->desk->id]);
    $ticket->recordEvent(TicketEventType::COMMENT_ADDED, $this->agent);

    $this->actingAs($this->admin)->get("$this->web?tab=tickets")->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'tickets')
        ->where('events.data.0.ticket_id', $ticket->id)
        ->where('events.data.0.type', 'comment_added'));

    $this->get("$this->web?tab=tickets&type=comment_added&actor_id={$this->agent->id}")->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
    $this->get("$this->web?tab=tickets&type=deleted")->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
});

test('the API lists audit and ticket events with cursor pagination', function () {
    actAs($this->admin, $this->workspace);
    $this->workspace->execute(function () {
        foreach (range(1, 52) as $i) {
            AuditEvent::record(AuditAction::DESK_UPDATED, $this->admin);
        }
    });

    $first = $this->getJson("$this->api/audit-events")->assertOk()->assertJsonCount(50, 'data');
    expect($first->json('meta.next_cursor'))->not->toBeNull();

    $this->getJson("$this->api/audit-events?cursor={$first->json('meta.next_cursor')}")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("$this->api/audit-events?action=workspace_updated")->assertOk()->assertJsonCount(0, 'data');

    $ticket = Ticket::factory()->create(['desk_id' => $this->desk->id]);
    $ticket->recordEvent(TicketEventType::DELETED, $this->admin);
    $this->getJson("$this->api/ticket-events?type=deleted")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ticket_id', $ticket->id);
});

test('agents cannot read the audit log', function () {
    $this->actingAs($this->agent)->get($this->web)->assertForbidden();
    $this->get("$this->web?tab=tickets")->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->getJson("$this->api/audit-events")->assertForbidden();
    $this->getJson("$this->api/ticket-events")->assertForbidden();
});

test('another workspace\'s events are never visible', function () {
    $this->actingAs($this->admin)->get($this->web)->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
    $this->get("$this->web?tab=tickets")->assertInertia(fn (Assert $page) => $page->has('events.data', 0));

    actAs($this->admin, $this->workspace);
    $this->getJson("$this->api/audit-events")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("$this->api/ticket-events")->assertOk()->assertJsonCount(0, 'data');

    // The other workspace's admin sees their own events there, and the tenant scope holds.
    expect($this->other->execute(fn () => AuditEvent::count()))->toBe(1);
    actAs($this->otherAdmin, $this->other);
    $this->getJson('http://other.harbor.test/api/v1/audit-events')->assertOk()->assertJsonCount(1, 'data');
});

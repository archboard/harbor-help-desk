<?php

use App\Enums\AuditAction;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Mcp\Servers\HarborServer;
use App\Mcp\Tools\ApplyMacroTool;
use App\Mcp\Tools\BulkUpdateTicketsTool;
use App\Mcp\Tools\CreateTicketTool;
use App\Mcp\Tools\MoveTicketTool;
use App\Mcp\Tools\ReplyToTicketTool;
use App\Mcp\Tools\SetReminderTool;
use App\Mcp\Tools\SetTicketAssigneesTool;
use App\Mcp\Tools\SetTicketTagsTool;
use App\Mcp\Tools\UpdateTicketTool;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/*
 * Ids in a request body or query that route binding can't catch. Workspace B owns the foreign
 * records, and the acting agent is a member of both workspaces, the worst case for leaks.
 */
beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->acme, 'desk' => $this->acmeDesk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->ticket = Ticket::factory()->for($this->acmeDesk)->create();

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other->users()->attach($this->agent, ['role' => WorkspaceRole::ADMIN]);
    $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::AGENT]);
    $this->foreign = $this->other->execute(function () {
        $desk = Desk::factory()->create();
        $desk->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $desk->workspace_id]);

        return (object) [
            'desk' => $desk,
            'ticket' => Ticket::factory()->for($desk)->create(['subject' => 'Foreign matter']),
            'macro' => Macro::factory()->create(),
            'contact' => Contact::factory()->create(),
            'stranger' => deskMember($this->other, $desk),
        ];
    });
    $this->acme->makeCurrent();

    $this->web = 'http://acme.harbor.test';
});

test('moving a ticket to another workspace\'s desk fails on web and API', function () {
    $this->actingAs($this->admin);
    $this->put("{$this->web}/tickets/{$this->ticket->id}/desk", ['desk_id' => $this->foreign->desk->id])->assertNotFound();

    actAs($this->admin, $this->acme);
    $this->putJson("{$this->web}/api/v1/tickets/{$this->ticket->id}/desk", ['desk_id' => $this->foreign->desk->id])->assertNotFound();

    expect($this->ticket->refresh()->desk_id)->toBe($this->acmeDesk->id);
});

test('assigning a user from another workspace fails on web and API', function () {
    $this->actingAs($this->admin);
    $this->put("{$this->web}/tickets/{$this->ticket->id}/assignees", ['user_ids' => [$this->foreign->stranger->id]])->assertSessionHasErrors('user_ids');

    actAs($this->admin, $this->acme);
    $this->putJson("{$this->web}/api/v1/tickets/{$this->ticket->id}/assignees", ['user_ids' => [$this->foreign->stranger->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('user_ids');

    expect($this->ticket->assignees()->count())->toBe(0);
});

test('applying another workspace\'s macro fails on web and API', function () {
    $this->actingAs($this->admin);
    $this->post("{$this->web}/tickets/{$this->ticket->id}/macros/{$this->foreign->macro->id}/apply")->assertNotFound();

    actAs($this->admin, $this->acme);
    $this->postJson("{$this->web}/api/v1/tickets/{$this->ticket->id}/macros/{$this->foreign->macro->id}/apply")->assertNotFound();
});

test('a desk member route rejects a user who is not in the workspace', function () {
    $this->actingAs($this->admin);
    $this->put("{$this->web}/desks/{$this->acmeDesk->id}/members/{$this->foreign->stranger->id}", ['role' => 'agent'])->assertSessionHasErrors('user');

    actAs($this->admin, $this->acme);
    $this->putJson("{$this->web}/api/v1/desks/{$this->acmeDesk->id}/members/{$this->foreign->stranger->id}", ['role' => 'agent'])
        ->assertUnprocessable()->assertJsonValidationErrors('user');

    expect($this->acmeDesk->users()->whereKey($this->foreign->stranger->id)->exists())->toBeFalse();
});

test('MCP write tools reject ids from another workspace', function () {
    $call = fn (string $tool, array $arguments) => HarborServer::actingAs($this->agent)->tool($tool, $arguments);
    $foreignTicket = $this->foreign->ticket->id;

    $call(MoveTicketTool::class, ['ticket' => $this->ticket->id, 'desk_id' => $this->foreign->desk->id])->assertHasErrors();
    $call(MoveTicketTool::class, ['ticket' => $foreignTicket, 'desk_id' => $this->acmeDesk->id])->assertHasErrors();
    $call(SetTicketAssigneesTool::class, ['ticket' => $this->ticket->id, 'user_ids' => [$this->foreign->stranger->id]])->assertHasErrors();
    $call(SetTicketAssigneesTool::class, ['ticket' => $foreignTicket, 'user_ids' => [$this->agent->id]])->assertHasErrors();
    $call(ApplyMacroTool::class, ['ticket' => $this->ticket->id, 'macro' => $this->foreign->macro->id])->assertHasErrors();
    $call(ApplyMacroTool::class, ['ticket' => $foreignTicket, 'macro' => $this->foreign->macro->id])->assertHasErrors();
    $call(CreateTicketTool::class, ['desk_id' => $this->foreign->desk->id, 'subject' => 'Leak', 'requester_id' => $this->foreign->contact->id])->assertHasErrors();
    $call(CreateTicketTool::class, ['desk_id' => $this->acmeDesk->id, 'subject' => 'Leak', 'requester_id' => $this->foreign->contact->id])->assertHasErrors();
    $call(UpdateTicketTool::class, ['ticket' => $foreignTicket, 'subject' => 'Hacked'])->assertHasErrors();
    $call(ReplyToTicketTool::class, ['ticket' => $foreignTicket, 'body' => 'Hacked'])->assertHasErrors();
    $call(SetTicketTagsTool::class, ['ticket' => $foreignTicket, 'names' => ['hacked']])->assertHasErrors();
    $call(SetReminderTool::class, ['ticket' => $foreignTicket, 'remind_at' => now()->addDay()->toIso8601String()])->assertHasErrors();
    $call(BulkUpdateTicketsTool::class, ['ticket_ids' => [$foreignTicket], 'priority' => 'urgent'])->assertHasErrors();

    expect($this->ticket->refresh()->desk_id)->toBe($this->acmeDesk->id)
        ->and($this->ticket->assignees()->count())->toBe(0)
        ->and(Ticket::where('subject', 'Leak')->exists())->toBeFalse();

    $this->other->execute(function () use ($foreignTicket) {
        $foreign = Ticket::findOrFail($foreignTicket);
        expect($foreign->subject)->toBe('Foreign matter')
            ->and($foreign->desk_id)->toBe($this->foreign->desk->id)
            ->and($foreign->priority->value)->not->toBe('urgent')
            ->and($foreign->assignees()->count())->toBe(0)
            ->and($foreign->comments()->count())->toBe(0)
            ->and($foreign->reminders()->count())->toBe(0)
            ->and($foreign->tags()->count())->toBe(0);
    });
});

test('an OAuth state naming another workspace\'s desk or workspace creates no mailbox', function () {
    config(['services.google_mail' => ['client_id' => 'google-client', 'client_secret' => 'google-secret'], 'app.url' => 'http://harbor.test']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'access-1',
        'refresh_token' => 'refresh-1',
        'expires_in' => 3600,
        'id_token' => 'header.'.rtrim(strtr(base64_encode(json_encode(['email' => 'help@acme.com'])), '+/', '-_'), '=').'.signature',
    ])]);

    $state = fn (Workspace $workspace, User $user, string $deskId) => urlencode(Crypt::encrypt([
        'workspace_id' => $workspace->id,
        'default_desk_id' => $deskId,
        'user_id' => $user->id,
        'provider' => 'google',
        'expires_at' => now()->addMinutes(10)->getTimestamp(),
    ]));

    $failure = function (string $state): ?string {
        try {
            $this->withoutExceptionHandling()->get("http://harbor.test/mailboxes/oauth/callback?code=abc&state={$state}");
        } catch (Throwable $e) {
            return $e::class;
        }

        return null;
    };

    // A's workspace with B's desk, then B's workspace by a user who is only an agent there.
    expect($failure($state($this->acme, $this->admin, $this->foreign->desk->id)))->not->toBeNull()
        ->and($failure($state($this->other, $this->admin, $this->foreign->desk->id)))->not->toBeNull();

    expect(Mailbox::withoutGlobalScopes()->count())->toBe(0);
});

test('notification preferences are per workspace and settings pages show only the current workspace', function () {
    $this->actingAs($this->agent);
    $before = $this->agent->membership($this->other)->notification_preferences;

    $this->put("{$this->web}/settings/notifications", ['preferences' => ['assigned' => ['mail' => false]]]);

    expect($this->agent->membership($this->other)->notification_preferences)->toBe($before)
        ->and($this->agent->membership($this->acme)->notification_preferences['assigned']['mail'] ?? null)->toBeFalse();

    $this->other->execute(function () {
        Macro::factory()->create(['name' => 'Foreign macro zzz']);
        Webhook::factory()->create(['description' => 'Foreign hook zzz']);
    });
    $this->actingAs($this->admin);
    foreach (['settings/macros', 'settings/notifications', 'settings/sla-policies', 'settings/business-hours', 'workspace/webhooks', 'workspace/email', 'workspace/custom-fields', 'workspace/audit-log'] as $page) {
        $this->get("{$this->web}/{$page}")->assertOk()->assertDontSee('zzz')->assertDontSee('Foreign');
    }
});

test('API event, audit and delivery lists never include another workspace\'s rows, whatever the filters', function () {
    TicketEvent::factory()->create(['ticket_id' => $this->ticket->id]);
    AuditEvent::record(AuditAction::DESK_MEMBER_SET, $this->admin);
    $webhook = Webhook::factory()->create();
    WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

    $foreign = $this->other->execute(function () {
        $webhook = Webhook::factory()->create();

        return [
            'event' => TicketEvent::factory()->create(['ticket_id' => $this->foreign->ticket->id, 'actor_id' => $this->foreign->stranger->id]),
            'audit' => AuditEvent::record(AuditAction::DESK_MEMBER_SET, $this->foreign->stranger),
            'delivery' => WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]),
        ];
    });
    actAs($this->admin, $this->acme);

    $ids = fn (string $path) => collect($this->getJson("{$this->web}/api/v1/{$path}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('ticket-events'))->not->toContain($foreign['event']->id)->not->toBeEmpty()
        ->and($ids("ticket-events?actor_id={$this->foreign->stranger->id}"))->toBe([])
        ->and($ids("ticket-events?ticket_id={$this->foreign->ticket->id}"))->not->toContain($foreign['event']->id)
        ->and($ids('audit-events'))->not->toContain($foreign['audit']->id)->not->toBeEmpty()
        ->and($ids("audit-events?actor_id={$this->foreign->stranger->id}"))->toBe([])
        ->and($ids("webhooks/{$webhook->id}/deliveries"))->not->toContain($foreign['delivery']->id)->not->toBeEmpty();
});

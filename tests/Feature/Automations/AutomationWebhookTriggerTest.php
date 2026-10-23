<?php

use App\Actions\Automations\ReceiveAutomationWebhook;
use App\Actions\Tickets\DeleteTicket;
use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

const HOOK_FIND_BY_NUMBER = ['mode' => 'find', 'match_by' => 'number', 'path' => 'ticket.number'];

/**
 * @param  array<string, mixed>  $config
 * @param  list<array<string, mixed>>|null  $steps
 * @param  array<string, mixed>  $attributes
 */
function hookAutomation(array $config = HOOK_FIND_BY_NUMBER, ?array $steps = null, array $attributes = []): Automation
{
    $steps ??= [hookNote('Hello')];
    $token = 'secret-token-'.fake()->unique()->numerify('######');

    $automation = Automation::factory()->enabled()->create([
        'trigger_type' => 'webhook',
        'definition' => ['trigger' => ['type' => 'webhook', 'config' => $config], 'steps' => $steps],
        'webhook_token_hash' => hash('sha256', $token),
        ...$attributes,
    ]);
    hookToken($automation, $token);

    return $automation;
}

function hookToken(Automation $automation, ?string $set = null): string
{
    static $tokens = [];

    return $set === null ? $tokens[$automation->id] : $tokens[$automation->id] = $set;
}

/**
 * @return array<string, mixed>
 */
function hookNote(string $body, string $id = 'n'): array
{
    return ['id' => $id, 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => $body]];
}

/**
 * @param  array<string, mixed>|string  $payload
 */
function hookFire(Automation $automation, array|string $payload = [], ?string $token = null, string $host = 'harbor.test'): TestResponse
{
    $url = "http://$host/webhooks/automations/{$automation->id}/".($token ?? hookToken($automation));

    return is_array($payload)
        ? test()->postJson($url, $payload)
        : test()->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
}

function hookCreateConfig(Desk $desk, array $extra = []): array
{
    return ['mode' => 'create', 'desk_id' => $desk->id, 'subject_path' => 'subject', 'body_path' => 'message.text', 'requester_email_path' => 'customer.email', ...$extra];
}

function hookNotes(Ticket $ticket): array
{
    return $ticket->comments()->pluck('body')->all();
}

test('an admin rotates the secret and gets the url and token once, and the old token stops working', function () {
    $automation = hookAutomation();
    $ticket = Ticket::factory()->create();
    actAs($this->admin, $this->workspace);

    $response = $this->postJson("http://acme.harbor.test/api/v1/automations/{$automation->id}/webhook-token")->assertCreated()->assertJsonStructure(['url', 'token']);

    $token = $response->json('token');
    expect($response->json('url'))->toEndWith("/webhooks/automations/{$automation->id}/$token")
        ->and($automation->refresh()->webhook_token_hash)->toBe(hash('sha256', $token));

    hookFire($automation, ['ticket' => ['number' => $ticket->number]], hookToken($automation))->assertNotFound();
    hookFire($automation, ['ticket' => ['number' => $ticket->number]], $token)->assertAccepted();

    $this->getJson("http://acme.harbor.test/api/v1/automations/{$automation->id}")->assertOk()
        ->assertJsonPath('has_webhook_token', true)->assertJsonMissingPath('webhook_token_hash');
    expect($this->getJson("http://acme.harbor.test/api/v1/automations/{$automation->id}")->getContent())->not->toContain($token)->not->toContain($automation->webhook_token_hash);
});

test('the web endpoint rotates the secret too', function () {
    $automation = hookAutomation();
    $this->actingAs($this->admin)->postJson("http://acme.harbor.test/automations/{$automation->id}/webhook-token")->assertCreated()->assertJsonStructure(['url', 'token']);
});

test('only editors of the automation can rotate its secret, and only webhook automations have one', function () {
    $otherDesk = Desk::factory()->create();
    $scoped = hookAutomation(attributes: ['desk_id' => $otherDesk->id]);
    $lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $agent = deskMember($this->workspace, $otherDesk);
    $plain = Automation::factory()->create();

    actAs($lead, $this->workspace);
    $this->postJson("http://acme.harbor.test/api/v1/automations/{$scoped->id}/webhook-token")->assertForbidden();

    actAs($agent, $this->workspace);
    $this->postJson("http://acme.harbor.test/api/v1/automations/{$scoped->id}/webhook-token")->assertForbidden();

    actAs($this->admin, $this->workspace);
    $this->postJson("http://acme.harbor.test/api/v1/automations/{$plain->id}/webhook-token")->assertUnprocessable()->assertJsonValidationErrors('automation');

    actAs(deskMember($this->workspace, $otherDesk, DeskRole::LEAD), $this->workspace);
    $this->postJson("http://acme.harbor.test/api/v1/automations/{$scoped->id}/webhook-token")->assertCreated();
});

test('moving an automation off the webhook trigger clears its secret', function () {
    $automation = hookAutomation();
    actAs($this->admin, $this->workspace);

    $this->putJson("http://acme.harbor.test/api/v1/automations/{$automation->id}", [
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [hookNote('Hi')]],
    ])->assertOk()->assertJsonPath('has_webhook_token', false);

    expect($automation->refresh()->webhook_token_hash)->toBeNull();
});

test('a bad token, unknown, disabled, non-webhook or token-less automation all get the same 404', function () {
    $ticket = Ticket::factory()->create();
    $payload = ['ticket' => ['number' => $ticket->number]];
    $good = hookAutomation();
    $disabled = hookAutomation(attributes: ['enabled' => false]);
    $notWebhook = Automation::factory()->enabled()->create(['webhook_token_hash' => hash('sha256', 'abc')]);
    $noHash = hookAutomation(attributes: ['webhook_token_hash' => null]);
    hookToken($notWebhook, 'abc');

    $responses = [
        hookFire($good, $payload, 'wrong'),
        hookFire($good, $payload, ''.str_repeat('x', 40)),
        $this->postJson('http://harbor.test/webhooks/automations/aut_nope/whatever', $payload),
        hookFire($disabled, $payload),
        hookFire($notWebhook, $payload),
        hookFire($noHash, $payload, 'abc'),
    ];

    foreach ($responses as $response) {
        $response->assertNotFound();
    }
    // Debug pages carry stack traces, so the bodies are compared without them.
    expect(collect($responses)->map(fn (TestResponse $response) => $response->json('message'))->unique())->toHaveCount(1)
        ->and($ticket->comments()->count())->toBe(0);
});

test('it is throttled per automation', function () {
    $automation = hookAutomation();
    $ticket = Ticket::factory()->create();
    $payload = ['ticket' => ['number' => $ticket->number]];

    foreach (range(1, ReceiveAutomationWebhook::MAX_PER_MINUTE) as $ignored) {
        RateLimiter::hit("automation-webhook:{$automation->id}", 60);
    }

    hookFire($automation, $payload)->assertStatus(429)->assertHeader('Retry-After');
    hookFire(hookAutomation(), $payload)->assertAccepted();
});

test('an oversized or malformed body is rejected', function () {
    $automation = hookAutomation();

    hookFire($automation, ['x' => str_repeat('a', ReceiveAutomationWebhook::MAX_BODY_BYTES)])->assertStatus(413);
    hookFire($automation, 'not json')->assertUnprocessable()->assertJsonValidationErrors('payload');
    hookFire($automation, '42')->assertUnprocessable()->assertJsonValidationErrors('payload');
    hookFire($automation, '"text"')->assertUnprocessable();
});

test('find acts on the ticket with that number or id and returns the run id', function () {
    $ticket = Ticket::factory()->create();
    $other = Ticket::factory()->create();

    $byNumber = hookAutomation(steps: [hookNote('By number')]);
    $response = hookFire($byNumber, ['ticket' => ['number' => $ticket->number]])->assertAccepted();
    $run = AutomationRun::sole();
    expect($response->json('run_id'))->toBe($run->id)
        ->and($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->ticket_id)->toBe($ticket->id)
        ->and(hookNotes($ticket))->toBe(['By number'])
        ->and(hookNotes($other))->toBe([]);

    $byId = hookAutomation(['mode' => 'find', 'match_by' => 'id', 'path' => 'id'], [hookNote('By id')]);
    hookFire($byId, ['id' => $other->id])->assertAccepted();
    expect(hookNotes($other))->toBe(['By id']);
});

test('find rejects a ticket that is missing, deleted or on another desk than a desk-scoped automation', function () {
    $otherDesk = Desk::factory()->create();
    $onOtherDesk = Ticket::factory()->create(['desk_id' => $otherDesk->id]);
    $deleted = Ticket::factory()->create();
    app(DeleteTicket::class)->handle($this->admin, $deleted);
    $scoped = hookAutomation(attributes: ['desk_id' => $this->desk->id]);

    hookFire($scoped, ['ticket' => ['number' => $onOtherDesk->number]])->assertUnprocessable()->assertJsonValidationErrors('payload');
    hookFire(hookAutomation(), ['ticket' => ['number' => $deleted->number]])->assertUnprocessable();
    hookFire(hookAutomation(), ['ticket' => ['number' => 999999]])->assertUnprocessable();
    hookFire(hookAutomation(), ['ticket' => ['number' => 'abc']])->assertUnprocessable();
    hookFire(hookAutomation(), ['other' => 1])->assertUnprocessable();

    expect(AutomationRun::count())->toBe(0);
});

test('create opens a ticket from the payload as the automation with source webhook', function () {
    $automation = hookAutomation(hookCreateConfig($this->desk, ['requester_name_path' => 'customer.name']));
    $payload = ['subject' => 'Broken order', 'message' => ['text' => 'It broke'], 'customer' => ['email' => 'Ann@Example.com', 'name' => 'Ann']];

    hookFire($automation, $payload)->assertAccepted();
    hookFire($automation, $payload)->assertAccepted();

    $tickets = Ticket::query()->where('subject', 'Broken order')->oldest('id')->get();
    $ticket = $tickets->first();
    $run = AutomationRun::query()->oldest('id')->first();

    expect($tickets)->toHaveCount(2)
        ->and($ticket->source)->toBe(TicketSource::WEBHOOK)
        ->and($ticket->desk_id)->toBe($this->desk->id)
        ->and($ticket->requester->email)->toBe('Ann@Example.com')
        ->and(Contact::query()->whereRaw('lower(email) = ?', ['ann@example.com'])->count())->toBe(1)
        ->and($ticket->events()->where('type', 'created')->sole()->actor->is($automation))->toBeTrue()
        ->and($run->ticket_id)->toBe($ticket->id)
        ->and(hookNotes($ticket))->toContain('It broke', 'Hello');
});

test('create without a valid requester email or subject is a validation error and leaves nothing behind', function () {
    $automation = hookAutomation(hookCreateConfig($this->desk));

    hookFire($automation, ['subject' => 'No email', 'message' => ['text' => 'x']])->assertUnprocessable()->assertJsonValidationErrors('requester.email');
    hookFire($automation, ['customer' => ['email' => 'a@example.com']])->assertUnprocessable()->assertJsonValidationErrors('subject');

    expect(Ticket::count())->toBe(0)->and(AutomationRun::count())->toBe(0);
});

test('a created ticket never retriggers the webhook automation, while a ticket.created automation for webhook tickets runs', function () {
    $webhook = hookAutomation(hookCreateConfig($this->desk), [hookNote('From webhook')]);
    $created = Automation::factory()->enabled()->create([
        'trigger_type' => 'ticket.created',
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => ['sources' => ['webhook']]], 'steps' => [hookNote('Saw webhook ticket')]],
    ]);
    $unrelated = Automation::factory()->enabled()->create([
        'trigger_type' => 'ticket.created',
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => ['sources' => ['email']]], 'steps' => [hookNote('Email only')]],
    ]);

    hookFire($webhook, ['subject' => 'S', 'message' => ['text' => 'B'], 'customer' => ['email' => 'a@example.com']])->assertAccepted();

    $ticket = Ticket::sole();
    $childRun = AutomationRun::query()->where('automation_id', $created->id)->sole();
    expect(AutomationRun::query()->where('automation_id', $webhook->id)->count())->toBe(1)
        ->and($childRun->depth)->toBe(1)
        ->and($childRun->parent_run_id)->toBe(AutomationRun::query()->where('automation_id', $webhook->id)->value('id'))
        ->and(AutomationRun::query()->where('automation_id', $unrelated->id)->count())->toBe(0)
        ->and(hookNotes($ticket))->toContain('From webhook', 'Saw webhook ticket')->not->toContain('Email only');
});

test('payload values fill placeholders and conditions, and survive a delay', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
    $steps = [
        ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'webhook.payload_number.order.total', 'operator' => 'gt', 'value' => 100]]],
        hookNote('Order {{ webhook.payload.order.id }} paid={{ webhook.payload.paid }} {{ webhook.payload.order.missing }}', 'n1'),
        ['id' => 'd', 'type' => 'delay', 'cancel_on' => [], 'for' => ['amount' => 1, 'unit' => 'hours']],
        hookNote('Later {{ webhook.payload.order.id }}', 'n2'),
    ];
    $automation = hookAutomation(steps: $steps);
    $number = ['ticket' => ['number' => $ticket->number]];

    hookFire($automation, [...$number, 'order' => ['id' => 'A-7', 'total' => 50]])->assertAccepted();
    expect(hookNotes($ticket))->toBe([]);

    hookFire($automation, [...$number, 'paid' => true, 'order' => ['id' => 'A-9', 'total' => 150]])->assertAccepted();
    expect(hookNotes($ticket))->toBe(['Order A-9 paid=true {{ webhook.payload.order.missing }}']);

    $run = AutomationRun::query()->where('status', AutomationRunStatus::WAITING)->sole();
    expect($run->state['payload']['order']['id'])->toBe('A-9');

    $this->travel(61)->minutes();
    $this->artisan('automations:resume')->assertSuccessful();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and(hookNotes($ticket))->toContain('Later A-9');
});

test('webhook payload fields are only available to the webhook trigger, and create is limited to the editor and the automation desk', function () {
    actAs($this->admin, $this->workspace);
    $url = 'http://acme.harbor.test/api/v1/automations';
    $condition = ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'webhook.payload.a', 'operator' => 'is', 'value' => 'x']]];

    $this->postJson($url, ['name' => 'X', 'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [$condition]]])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.c.rules.0.field']);
    $this->postJson($url, ['name' => 'X', 'definition' => ['trigger' => ['type' => 'webhook', 'config' => HOOK_FIND_BY_NUMBER], 'steps' => [$condition]]])->assertCreated();

    $otherWorkspaceDesk = Workspace::factory()->create(['subdomain' => 'other'])->execute(fn () => Desk::sole());
    $this->postJson($url, ['name' => 'X', 'definition' => ['trigger' => ['type' => 'webhook', 'config' => hookCreateConfig($otherWorkspaceDesk)], 'steps' => []]])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.trigger.config.desk_id']);

    $second = Desk::factory()->create();
    $this->postJson($url, ['name' => 'X', 'desk_id' => $this->desk->id, 'definition' => ['trigger' => ['type' => 'webhook', 'config' => hookCreateConfig($second)], 'steps' => []]])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.trigger.config.desk_id']);

    $lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    actAs($lead, $this->workspace);
    $this->postJson($url, ['name' => 'X', 'desk_id' => $this->desk->id, 'definition' => ['trigger' => ['type' => 'webhook', 'config' => hookCreateConfig($this->desk)], 'steps' => []]])->assertCreated();
    $this->postJson($url, ['name' => 'X', 'desk_id' => $this->desk->id, 'definition' => ['trigger' => ['type' => 'webhook', 'config' => ['mode' => 'find', 'match_by' => 'number']], 'steps' => []]])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.trigger.config.path']);
});

test('it works for a self-hosted single workspace without subdomains', function () {
    config(['harbor.multitenant' => false]);
    $ticket = Ticket::factory()->create();
    $automation = hookAutomation();

    hookFire($automation, ['ticket' => ['number' => $ticket->number]], host: 'helpdesk.example.com')->assertAccepted();

    expect(hookNotes($ticket))->toBe(['Hello']);
});

describe('tenancy', function () {
    beforeEach(function () {
        $this->other = Workspace::factory()->create(['subdomain' => 'other']);
        $this->adminB = User::factory()->create();
        $this->other->users()->attach($this->adminB, ['role' => WorkspaceRole::ADMIN]);
    });

    test('a token never resolves another workspace\'s automation', function () {
        $a = hookAutomation();
        $b = $this->other->execute(fn () => hookAutomation());

        hookFire($b, [], hookToken($a))->assertNotFound();
        hookFire($a, [], hookToken($b))->assertNotFound();
        $this->postJson("http://harbor.test/webhooks/automations/{$b->id}/{hookToken($a)}", [])->assertNotFound();
    });

    test('the secret of another workspace\'s automation can not be rotated', function () {
        $b = $this->other->execute(fn () => hookAutomation());
        $hash = $b->webhook_token_hash;
        $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

        $this->actingAs($this->admin)->postJson("http://acme.harbor.test/automations/{$b->id}/webhook-token")->assertNotFound();

        expect($this->other->execute(fn () => $b->fresh()->webhook_token_hash))->toBe($hash);
    });

    test('find only ever acts on the automation\'s own workspace, even for colliding numbers and B\'s host', function () {
        $a = hookAutomation(steps: [hookNote('A note')]);
        $ticketA = Ticket::factory()->create();
        $ticketB = $this->other->execute(fn () => Ticket::factory()->create(['number' => $ticketA->number]));
        $onlyB = $this->other->execute(fn () => Ticket::factory()->create(['number' => 77]));
        expect($ticketB->number)->toBe($ticketA->number);

        hookFire($a, ['ticket' => ['number' => $ticketA->number]], host: 'other.harbor.test')->assertAccepted();
        expect(hookNotes($ticketA))->toBe(['A note'])
            ->and($this->other->execute(fn () => hookNotes($ticketB->refresh())))->toBe([]);

        hookFire($a, ['ticket' => ['number' => $onlyB->number]])->assertUnprocessable();
        $this->other->execute(fn () => expect(AutomationRun::count())->toBe(0)->and(hookNotes($onlyB))->toBe([]));
    });

    test('create lands in the automation\'s workspace and the current workspace is not left set', function () {
        $a = hookAutomation(hookCreateConfig($this->desk));
        Workspace::forgetCurrent();

        hookFire($a, ['subject' => 'Hi', 'message' => ['text' => 'B'], 'customer' => ['email' => 'a@example.com']], host: 'other.harbor.test')->assertAccepted();

        expect(Workspace::current())->toBeNull();

        $ticket = $this->workspace->execute(fn () => Ticket::sole());
        expect($ticket->workspace_id)->toBe($this->workspace->id)
            ->and($this->other->execute(fn () => Ticket::count()))->toBe(0)
            ->and($this->other->execute(fn () => Contact::count()))->toBe(0);

        actAs($this->adminB, $this->other);
        $this->getJson('http://other.harbor.test/api/v1/tickets')->assertOk()->assertJsonCount(0, 'data');
    });

    test('a request keeps a workspace that was already current', function () {
        $a = hookAutomation();
        $this->other->makeCurrent();

        hookFire($a, ['x' => 1])->assertUnprocessable();

        expect(Workspace::current()->is($this->other))->toBeTrue();
    });

    test('rotating B\'s secret through A\'s host is a 404', function () {
        $b = $this->other->execute(fn () => hookAutomation());
        actAs($this->admin, $this->workspace);

        $this->postJson("http://acme.harbor.test/api/v1/automations/{$b->id}/webhook-token")->assertNotFound();
        expect($b->refresh()->webhook_token_hash)->toBe(hash('sha256', hookToken($b)));
    });
});

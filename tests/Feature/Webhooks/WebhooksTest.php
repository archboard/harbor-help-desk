<?php

use App\Actions\Webhooks\DeliverWebhook;
use App\Automations\SafeUrl;
use App\Data\Webhooks\WebhookData;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use App\Notifications\WebhookDisabledNotification;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->acme, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->otherAdmin = User::factory()->create();
    $this->other->users()->attach($this->otherAdmin, ['role' => WorkspaceRole::ADMIN]);

    $dns = ['hooks.example.com' => ['93.184.216.34'], 'internal.example.com' => ['10.0.0.5']];
    app()->instance(SafeUrl::class, new SafeUrl(fn (string $host): array => $dns[$host] ?? []));

    Http::preventStrayRequests();
    fakeHooks(Http::response('ok'));

    $this->api = 'http://acme.harbor.test/api/v1/webhooks';
    $this->hook = Webhook::factory()->create(['created_by' => $this->admin->id]);
});

/**
 * Record an event on a new ticket of the current workspace.
 */
function recordTicketEvent(TicketEventType $type = TicketEventType::COMMENT_ADDED): void
{
    Ticket::factory()->create(['desk_id' => Desk::sole()->id])->recordEvent($type, null);
}

/**
 * Replaces the Http fake, since the first stub that matches wins.
 */
function fakeHooks(mixed $response): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['hooks.example.com/*' => $response]);
}

/**
 * @return list<Request>
 */
function sentWebhooks(): array
{
    return Http::recorded()->map(fn (array $pair): Request => $pair[0])->values()->all();
}

test('a ticket event is sent with a signature that verifies against the secret', function () {
    recordTicketEvent();

    expect(sentWebhooks())->toHaveCount(1);
    $request = sentWebhooks()[0];
    $delivery = WebhookDelivery::sole();

    preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $request->header('Harbor-Signature')[0], $match);
    expect($match[2])->toBe(hash_hmac('sha256', "{$match[1]}.{$request->body()}", $this->hook->secret))
        ->and($request->header('Harbor-Delivery')[0])->toBe($delivery->id);

    $payload = json_decode($request->body(), true);
    expect($payload)->toMatchArray(['id' => $delivery->id, 'type' => 'comment_added', 'workspace_id' => $this->acme->id])
        ->and($payload['event']['type'])->toBe('comment_added')
        ->and($payload['ticket']['id'])->toBe($delivery->ticketEvent->ticket_id)
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::SUCCEEDED)
        ->and($delivery->response_status)->toBe(200);
});

test('only subscribed event types are sent', function () {
    $this->hook->update(['event_types' => ['created']]);

    recordTicketEvent(TicketEventType::COMMENT_ADDED);
    expect(sentWebhooks())->toBeEmpty()->and(WebhookDelivery::count())->toBe(0);

    recordTicketEvent(TicketEventType::CREATED);
    expect(sentWebhooks())->toHaveCount(1);
});

test('disabled webhooks are skipped', function () {
    $this->hook->update(['is_enabled' => false]);

    recordTicketEvent();

    expect(sentWebhooks())->toBeEmpty()->and(WebhookDelivery::count())->toBe(0);
});

test('events of another workspace never reach this workspace\'s webhooks', function () {
    $this->other->execute(function () {
        Webhook::factory()->create(['url' => 'https://hooks.example.com/other']);
        recordTicketEvent();
    });

    expect(sentWebhooks())->toHaveCount(1)
        ->and(sentWebhooks()[0]->url())->toBe('https://hooks.example.com/other')
        ->and(WebhookDelivery::count())->toBe(0);
});

test('a webhook pointing at a private address fails without sending', function () {
    $this->hook->update(['url' => 'https://internal.example.com/hook']);

    recordTicketEvent();

    $delivery = WebhookDelivery::sole();
    expect(sentWebhooks())->toBeEmpty()
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::FAILED)
        ->and($delivery->error)->toContain('private or reserved')
        ->and($this->hook->fresh()->consecutive_failures)->toBe(1);
});

test('private URLs are rejected when saving a webhook in multitenant mode', function () {
    actAs($this->admin, $this->acme);

    $this->postJson($this->api, ['url' => 'https://internal.example.com/hook'])->assertJsonValidationErrors('url');
    $this->postJson($this->api, ['url' => 'http://127.0.0.1/hook'])->assertJsonValidationErrors('url');
    $this->patchJson("{$this->api}/{$this->hook->id}", ['url' => 'https://internal.example.com/hook'])->assertJsonValidationErrors('url');
});

test('server errors are retried, then count as one failure', function () {
    fakeHooks(Http::response('', 503));
    $delivery = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id, 'event_type' => 'comment_added']);

    expect(app(DeliverWebhook::class)->handle($delivery, 1, false))->toBeTrue();
    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::PENDING)
        ->and($this->hook->fresh()->consecutive_failures)->toBe(0);

    expect(app(DeliverWebhook::class)->handle($delivery, 3, true))->toBeFalse();
    expect($delivery->fresh())->status->toBe(WebhookDeliveryStatus::FAILED)->attempts->toBe(3)->response_status->toBe(503)
        ->and($this->hook->fresh()->consecutive_failures)->toBe(1);
});

test('a client error is final and a success resets the count', function () {
    $this->hook->update(['consecutive_failures' => 5]);
    fakeHooks(Http::sequence()->push('', 404)->push('ok'));

    $first = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id, 'event_type' => 'comment_added']);
    expect(app(DeliverWebhook::class)->handle($first, 1, false))->toBeFalse()
        ->and($this->hook->fresh()->consecutive_failures)->toBe(6);

    $second = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id, 'event_type' => 'comment_added']);
    app(DeliverWebhook::class)->handle($second, 1, false);
    expect($this->hook->fresh()->consecutive_failures)->toBe(0);
});

test('the twentieth failure in a row disables the webhook, audits it and tells the creator', function () {
    Notification::fake();
    fakeHooks(Http::response('', 400));
    $this->hook->update(['consecutive_failures' => 19]);

    recordTicketEvent();

    $hook = $this->hook->fresh();
    expect($hook->is_enabled)->toBeFalse()->and($hook->disabled_at)->not->toBeNull()
        ->and(AuditEvent::where('action', AuditAction::WEBHOOK_DISABLED)->count())->toBe(1);
    Notification::assertSentToTimes($this->admin, WebhookDisabledNotification::class, 1);
});

test('old deliveries are pruned', function () {
    $old = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id, 'created_at' => now()->subDays(31)]);
    $recent = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id]);

    $this->artisan('model:prune', ['--model' => WebhookDelivery::class])->assertSuccessful();

    expect(WebhookDelivery::find($old->id))->toBeNull()->and(WebhookDelivery::find($recent->id))->not->toBeNull();
});

test('the secret is shown once on create and rotate and nowhere else', function () {
    actAs($this->admin, $this->acme);

    $created = $this->postJson($this->api, ['url' => 'https://hooks.example.com/new', 'event_types' => ['created'], 'description' => 'CRM'])->assertCreated();
    $secret = $created->json('secret');
    expect($secret)->toStartWith('whsec_')
        ->and($created->json('webhook.event_types'))->toBe(['created']);
    $id = $created->json('webhook.id');

    $this->getJson("{$this->api}/{$id}")->assertOk()->assertJsonMissingPath('secret');
    expect($this->getJson($this->api)->getContent())->not->toContain($secret);

    $rotated = $this->postJson("{$this->api}/{$id}/secret")->assertCreated();
    expect($rotated->json('secret'))->toStartWith('whsec_')->not->toBe($secret)
        ->and(Webhook::find($id)->secret)->toBe($rotated->json('secret'));

    expect(json_encode(WebhookData::from(Webhook::find($id))))->not->toContain($rotated->json('secret'));
    expect(json_encode(AuditEvent::all()->map->properties))->not->toContain('whsec_');
    expect(AuditEvent::pluck('action')->all())->toContain(AuditAction::WEBHOOK_CREATED, AuditAction::WEBHOOK_SECRET_ROTATED);
    expect(DB::table('webhooks')->where('id', $id)->value('secret'))->not->toContain('whsec_');
});

test('updating, re-enabling and deleting a webhook', function () {
    actAs($this->admin, $this->acme);
    $this->hook->update(['is_enabled' => false, 'consecutive_failures' => 20, 'disabled_at' => now()]);

    $this->patchJson("{$this->api}/{$this->hook->id}", ['is_enabled' => true, 'event_types' => ['status_changed']])
        ->assertOk()->assertJsonPath('is_enabled', true)->assertJsonPath('consecutive_failures', 0)->assertJsonPath('disabled_at', null)
        ->assertJsonPath('event_types', ['status_changed']);

    $this->patchJson("{$this->api}/{$this->hook->id}", ['event_types' => ['nonsense']])->assertJsonValidationErrors('event_types.0');

    $this->deleteJson("{$this->api}/{$this->hook->id}")->assertNoContent();
    expect(Webhook::count())->toBe(0)
        ->and(AuditEvent::pluck('action')->all())->toContain(AuditAction::WEBHOOK_UPDATED, AuditAction::WEBHOOK_DELETED);
});

test('a ping is delivered at once and listed with the deliveries', function () {
    actAs($this->admin, $this->acme);
    $this->hook->update(['is_enabled' => false]);

    $this->postJson("{$this->api}/{$this->hook->id}/ping")->assertCreated()
        ->assertJsonPath('event_type', 'ping')->assertJsonPath('status', 'succeeded')->assertJsonPath('ticket_event_id', null);

    $payload = json_decode(sentWebhooks()[0]->body(), true);
    expect($payload['type'])->toBe('ping')->and($payload['ticket'])->toBeNull();

    $this->getJson("{$this->api}/{$this->hook->id}/deliveries")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.event_type', 'ping');
});

test('a failed ping does not count towards disabling', function () {
    actAs($this->admin, $this->acme);
    fakeHooks(Http::response('', 500));

    $this->postJson("{$this->api}/{$this->hook->id}/ping")->assertCreated()->assertJsonPath('status', 'failed');

    expect($this->hook->fresh()->consecutive_failures)->toBe(0);
});

test('agents cannot use webhooks', function () {
    actAs($this->agent, $this->acme);
    $url = "{$this->api}/{$this->hook->id}";

    $this->getJson($this->api)->assertForbidden();
    $this->postJson($this->api, ['url' => 'https://hooks.example.com/x'])->assertForbidden();
    $this->getJson($url)->assertForbidden();
    $this->patchJson($url, ['is_enabled' => false])->assertForbidden();
    $this->deleteJson($url)->assertForbidden();
    $this->postJson("{$url}/secret")->assertForbidden();
    $this->postJson("{$url}/ping")->assertForbidden();
    $this->getJson("{$url}/deliveries")->assertForbidden();
});

test('another workspace\'s admin cannot see or change these webhooks', function () {
    $theirs = $this->other->execute(fn () => Webhook::factory()->create());
    actAs($this->admin, $this->acme);
    $url = "{$this->api}/{$theirs->id}";

    $this->getJson($url)->assertNotFound();
    $this->patchJson($url, ['is_enabled' => false])->assertNotFound();
    $this->deleteJson($url)->assertNotFound();
    $this->postJson("{$url}/secret")->assertNotFound();
    $this->postJson("{$url}/ping")->assertNotFound();
    $this->getJson("{$url}/deliveries")->assertNotFound();
    $this->getJson($this->api)->assertJsonCount(1);

    expect($theirs->fresh()->consecutive_failures)->toBe(0);
});

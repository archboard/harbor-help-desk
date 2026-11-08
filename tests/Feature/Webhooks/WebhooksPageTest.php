<?php

use App\Automations\SafeUrl;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->acme, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $dns = ['hooks.example.com' => ['93.184.216.34']];
    app()->instance(SafeUrl::class, new SafeUrl(fn (string $host): array => $dns[$host] ?? []));

    Http::preventStrayRequests();
    Http::fake(['hooks.example.com/*' => Http::response('ok')]);

    $this->host = 'http://acme.harbor.test/workspace/webhooks';
    $this->hook = Webhook::factory()->create(['created_by' => $this->admin->id]);
});

test('the page lists webhooks, event type options and recent deliveries, never a secret', function () {
    $delivery = WebhookDelivery::factory()->create(['webhook_id' => $this->hook->id, 'status' => WebhookDeliveryStatus::SUCCEEDED]);
    $this->other->execute(fn () => Webhook::factory()->create(['url' => 'https://hooks.example.com/theirs']));

    $this->actingAs($this->admin)->get($this->host)
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/Webhooks')
            ->has('webhooks', 1)
            ->where('webhooks.0.id', $this->hook->id)
            ->missing('webhooks.0.secret')
            ->where("deliveries.{$this->hook->id}.0.id", $delivery->id)
            ->where('eventTypes.comment_added', 'Comment added')
            ->missing('webhookSecret'));
});

test('creating and rotating flash the secret once', function () {
    $this->actingAs($this->admin)
        ->post($this->host, ['url' => 'https://hooks.example.com/new', 'event_types' => ['comment_added']])
        ->assertRedirect()
        ->assertInertiaFlash('webhookSecret');

    $created = Webhook::query()->where('url', 'https://hooks.example.com/new')->sole();
    expect($created->event_types)->toBe(['comment_added'])
        ->and(session('inertia.flash_data')['webhookSecret']['secret'])->toBe($created->secret);

    $old = $this->hook->secret;
    $this->actingAs($this->admin)->post("{$this->host}/{$this->hook->id}/secret")->assertInertiaFlash('webhookSecret');

    expect($this->hook->fresh()->secret)->not->toBe($old);
});

test('an invalid url is reported', function () {
    $this->actingAs($this->admin)->post($this->host, ['url' => 'http://internal.example.com'])->assertSessionHasErrors('url');
});

test('update, ping and delete run their actions', function () {
    $url = "{$this->host}/{$this->hook->id}";

    $this->actingAs($this->admin)->patch($url, ['is_enabled' => false, 'description' => 'Changed'])->assertRedirect();
    expect($this->hook->fresh())->is_enabled->toBeFalse()->description->toBe('Changed');

    $this->actingAs($this->admin)->post("{$url}/ping")->assertRedirect();
    expect($this->hook->deliveries()->where('event_type', WebhookDelivery::PING)->sole()->status)->toBe(WebhookDeliveryStatus::SUCCEEDED);

    $this->actingAs($this->admin)->delete($url)->assertRedirect();
    expect(Webhook::query()->count())->toBe(0);
});

test('agents can not see or change webhooks', function () {
    $url = "{$this->host}/{$this->hook->id}";
    $this->actingAs($this->agent);

    $this->get($this->host)->assertForbidden();
    $this->post($this->host, ['url' => 'https://hooks.example.com/new'])->assertForbidden();
    $this->patch($url, ['is_enabled' => false])->assertForbidden();
    $this->post("{$url}/secret")->assertForbidden();
    $this->post("{$url}/ping")->assertForbidden();
    $this->delete($url)->assertForbidden();

    expect($this->hook->fresh()->is_enabled)->toBeTrue();
});

test('another workspace\'s webhooks can not be changed from this one', function () {
    $theirs = $this->other->execute(fn () => Webhook::factory()->create());
    $url = "{$this->host}/{$theirs->id}";
    $this->actingAs($this->admin);

    $this->patch($url, ['is_enabled' => false])->assertNotFound();
    $this->post("{$url}/secret")->assertNotFound();
    $this->post("{$url}/ping")->assertNotFound();
    $this->delete($url)->assertNotFound();

    expect($theirs->fresh()->is_enabled)->toBeTrue();
});

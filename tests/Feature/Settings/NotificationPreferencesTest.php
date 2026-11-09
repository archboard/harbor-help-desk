<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
    $this->other->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->url = 'http://acme.harbor.test/api/v1/notification-preferences';
});

test('members get the enum defaults until they choose', function () {
    actAs($this->agent, $this->acme);

    $rows = collect($this->getJson($this->url)->assertOk()->json())->keyBy('type');

    expect($rows)->toHaveCount(count(NotificationType::cases()))
        ->and($rows['assigned']['channels'])->toBe(['database' => true, 'mail' => true])
        ->and($rows['new_ticket']['channels'])->toBe(['database' => true, 'mail' => true])
        ->and($this->agent->notificationChannels($this->acme, NotificationType::TICKET_UPDATED))->toBe(['database'])
        ->and($this->agent->notificationChannels($this->acme, NotificationType::MENTIONED))->toBe(['database', 'mail']);
});

test('an update merges into the current choices', function () {
    actAs($this->agent, $this->acme);

    $this->putJson($this->url, ['preferences' => ['assigned' => ['mail' => false]]])->assertOk();
    $this->putJson($this->url, ['preferences' => ['new_ticket' => ['mail' => false], 'assigned' => ['database' => false]]])
        ->assertOk()
        ->assertJsonPath('0.channels.mail', false);

    expect($this->agent->notificationChannels($this->acme, NotificationType::ASSIGNED))->toBe([])
        ->and($this->agent->notificationChannels($this->acme, NotificationType::NEW_TICKET))->toBe(['database'])
        ->and($this->agent->notificationChannels($this->acme, NotificationType::MENTIONED))->toBe(['database', 'mail']);
});

test('invalid types, channels and values are rejected', function (array $preferences, string $field) {
    actAs($this->agent, $this->acme);

    $this->putJson($this->url, ['preferences' => $preferences])->assertJsonValidationErrors($field);
})->with([
    'type' => [['nope' => ['mail' => true]], 'preferences'],
    'channel' => [['assigned' => ['sms' => true]], 'preferences.assigned'],
    'value' => [['assigned' => ['mail' => 'maybe']], 'preferences.assigned.mail'],
]);

test('preferences in one workspace do not affect another', function () {
    actAs($this->agent, $this->acme);
    $this->putJson($this->url, ['preferences' => ['assigned' => ['mail' => false]]])->assertOk();

    expect($this->agent->notificationChannels($this->other, NotificationType::ASSIGNED))->toBe(['database', 'mail']);
});

test('non-members have no channels', function () {
    $stranger = User::factory()->create();

    expect($stranger->notificationChannels($this->acme, NotificationType::ASSIGNED))->toBe([]);

    actAs($stranger, $this->acme);
    $this->getJson($this->url)->assertForbidden();
});

test('the settings page lists every type and channel', function () {
    config(['harbor.multitenant' => false]);
    $workspace = $this->acme->makeCurrent();
    $this->actingAs($this->agent);

    $this->get(route('notification-preferences.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Notifications')
            ->has('preferences', count(NotificationType::cases()))
            ->where('channels', NotificationChannel::options())
            ->where('hasNotificationMailbox', false));

    $this->put(route('notification-preferences.update'), ['preferences' => ['assigned' => ['mail' => false]]])
        ->assertSessionHasNoErrors();

    expect($this->agent->notificationChannels($workspace, NotificationType::ASSIGNED))->toBe(['database']);
});

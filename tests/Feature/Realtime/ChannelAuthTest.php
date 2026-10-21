<?php

use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    // The null broadcaster authorizes everything, so use Reverb with fake keys and re-register the channels on it.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $this->ticket = Ticket::factory()->create();
    $this->member = deskMember($this->workspace, $this->ticket->desk);
});

/**
 * @return TestResponse<Response>
 */
function joinChannel(User $user, string $channel, string $type = 'private'): TestResponse
{
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => "{$type}-{$channel}",
        'socket_id' => '1234.5678',
    ]);
}

test('a desk member can join the ticket and desk channels', function () {
    joinChannel($this->member, "tickets.{$this->ticket->id}")->assertOk()->assertJsonStructure(['auth']);
    joinChannel($this->member, "desks.{$this->ticket->desk_id}")->assertOk()->assertJsonStructure(['auth']);
});

test('a user from another desk cannot join', function () {
    $other = deskMember($this->workspace, Desk::factory()->create());

    joinChannel($other, "tickets.{$this->ticket->id}")->assertForbidden();
    joinChannel($other, "desks.{$this->ticket->desk_id}")->assertForbidden();
});

test('a user from workspace B cannot join workspace A channels', function () {
    $workspaceB = Workspace::factory()->create()->makeCurrent();
    $outsider = User::factory()->create();
    $workspaceB->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);

    $this->workspace->makeCurrent();

    joinChannel($outsider, "tickets.{$this->ticket->id}")->assertForbidden();
    joinChannel($outsider, "desks.{$this->ticket->desk_id}")->assertForbidden();
});

test('the presence channel returns the member as user data and keeps others out', function () {
    $presence = "tickets.{$this->ticket->id}.presence";

    $response = joinChannel($this->member, $presence, 'presence')->assertOk();
    expect(json_decode($response->json('channel_data'), true)['user_info'])
        ->toMatchArray(['id' => $this->member->id, 'name' => $this->member->name]);

    joinChannel(deskMember($this->workspace, Desk::factory()->create()), $presence, 'presence')->assertForbidden();

    $workspaceB = Workspace::factory()->create()->makeCurrent();
    $outsider = User::factory()->create();
    $workspaceB->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);
    $this->workspace->makeCurrent();

    joinChannel($outsider, $presence, 'presence')->assertForbidden();
});

test('a ticket from another workspace does not resolve', function () {
    Workspace::factory()->create()->makeCurrent();
    $ticketB = Ticket::factory()->create();

    $this->workspace->makeCurrent();

    joinChannel($this->member, "tickets.{$ticketB->id}")->assertStatus(403);
});

test('the user channel is self only', function () {
    $other = deskMember($this->workspace, $this->ticket->desk);

    joinChannel($this->member, "users.{$this->member->id}")->assertOk();
    joinChannel($this->member, "users.{$other->id}")->assertForbidden();
    expect($this->member->receivesBroadcastNotificationsOn())->toBe("users.{$this->member->id}");
});

test('the realtime prop holds the public settings and never the secret', function () {
    config([
        'broadcasting.connections.reverb.options' => ['host' => 'help.example.com', 'port' => 443, 'scheme' => 'https'],
    ]);

    $this->actingAs($this->member)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('realtime', ['key' => 'test-key', 'host' => 'help.example.com', 'port' => 443, 'scheme' => 'https'])
        );
});

test('guests get no realtime prop', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->missing('realtime'));
});

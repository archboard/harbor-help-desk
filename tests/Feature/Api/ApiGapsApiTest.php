<?php

use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->acme, 'desk' => $this->acmeDesk, 'agent' => $this->agent] = acmeWithAgent();
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other->users()->attach($this->agent, ['role' => 'agent']);
    $this->acme->makeCurrent();

    $this->url = 'http://acme.harbor.test/api/v1';
});

function apiNotification(User $user, Workspace $workspace): DatabaseNotification
{
    return $workspace->execute(fn () => $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'reminder_due', 'data' => []]));
}

test('tags lists the workspace tags by name and never another workspace\'s', function () {
    Tag::factory()->create(['name' => 'Zeta']);
    Tag::factory()->create(['name' => 'Alpha']);
    $this->other->execute(fn () => Tag::factory()->create(['name' => 'Secret']));
    actAs($this->agent, $this->acme);

    $this->getJson("{$this->url}/tags")->assertOk()->assertJsonCount(2)->assertJsonPath('0.name', 'Alpha')->assertJsonPath('1.name', 'Zeta');
});

test('tags requires a token', function () {
    $this->getJson("{$this->url}/tags")->assertUnauthorized();
});

test('a single notification is marked read, but not someone else\'s or another workspace\'s', function () {
    $mine = apiNotification($this->agent, $this->acme);
    $elsewhere = apiNotification($this->agent, $this->other);
    $colleague = apiNotification(User::factory()->create(), $this->acme);
    actAs($this->agent, $this->acme);

    $this->postJson("{$this->url}/notifications/{$colleague->id}/read")->assertNoContent();
    $this->postJson("{$this->url}/notifications/{$elsewhere->id}/read")->assertNoContent();
    expect($colleague->refresh()->read_at)->toBeNull()->and($elsewhere->refresh()->read_at)->toBeNull();

    $this->postJson("{$this->url}/notifications/{$mine->id}/read")->assertNoContent();
    expect($mine->refresh()->read_at)->not->toBeNull();
});

test('lookup finds tickets by number or subject and stays in the workspace', function () {
    $ticket = Ticket::factory()->for($this->acmeDesk)->create(['subject' => 'Printer jam']);
    $this->other->execute(fn () => Ticket::factory()->for(Desk::sole())->create(['subject' => 'Printer fire']));
    actAs($this->agent, $this->acme);

    $this->getJson("{$this->url}/tickets/lookup?search=printer")->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $ticket->id);
    $this->getJson("{$this->url}/tickets/lookup?search=%23{$ticket->number}")->assertOk()->assertJsonPath('0.id', $ticket->id);
    $this->getJson("{$this->url}/tickets/lookup")->assertOk()->assertJsonCount(0);
});

test('a user updates their own profile and timezone, re-verifying a changed email', function () {
    $this->agent->update(['email_verified_at' => now(), 'timezone' => null]);
    actAs($this->agent, $this->acme);

    $this->patchJson("{$this->url}/user", ['timezone' => 'Asia/Tokyo'])->assertOk()->assertJsonPath('name', $this->agent->name);
    expect($this->agent->refresh()->timezone)->toBe('Asia/Tokyo')->and($this->agent->email_verified_at)->not->toBeNull();

    $this->patchJson("{$this->url}/user", ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertOk()->assertJsonPath('name', 'New Name')->assertJsonPath('email', 'new@example.com');
    expect($this->agent->refresh()->email_verified_at)->toBeNull();
});

test('profile updates reject a taken email and a bad timezone but allow keeping your own email', function () {
    $taken = User::factory()->create();
    actAs($this->agent, $this->acme);

    $this->patchJson("{$this->url}/user", ['email' => $taken->email, 'timezone' => 'Mars/Olympus'])->assertJsonValidationErrors(['email', 'timezone']);
    $this->patchJson("{$this->url}/user", ['email' => $this->agent->email])->assertOk();
});

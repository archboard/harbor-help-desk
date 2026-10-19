<?php

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('logging in stores the browser timezone when the user has none', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'timezone' => 'America/New_York']);

    expect($user->fresh()->timezone)->toBe('America/New_York');
});

test('logging in keeps a saved timezone and ignores invalid ones', function () {
    $saved = User::factory()->create(['timezone' => 'Europe/London']);
    $unset = User::factory()->create(['timezone' => null]);

    $this->post(route('login.store'), ['email' => $saved->email, 'password' => 'password', 'timezone' => 'America/New_York']);
    auth()->logout();
    $this->post(route('login.store'), ['email' => $unset->email, 'password' => 'password', 'timezone' => 'Mars/Olympus']);

    expect($saved->fresh()->timezone)->toBe('Europe/London')
        ->and($unset->fresh()->timezone)->toBeNull();
});

test('the profile form and the prompt change the timezone', function () {
    $user = User::factory()->create(['timezone' => null]);
    $this->actingAs($user);

    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'timezone' => 'Asia/Tokyo'])
        ->assertSessionHasNoErrors();
    expect($user->fresh()->timezone)->toBe('Asia/Tokyo');

    $this->from(route('desks.index'))
        ->put(route('profile.timezone.update'), ['timezone' => 'Europe/Paris'])
        ->assertRedirect(route('desks.index'));
    expect($user->fresh()->timezone)->toBe('Europe/Paris');

    $this->put(route('profile.timezone.update'), ['timezone' => 'Nowhere/Special'])->assertSessionHasErrors('timezone');
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'timezone' => ''])->assertSessionHasErrors('timezone');
});

test('history times are shown in the viewer\'s timezone', function () {
    $ticket = Ticket::factory()->create();
    $event = $ticket->recordEvent(TicketEventType::DUE_DATE_CHANGED, null, ['from' => null, 'to' => '2026-10-19T18:30:00+00:00']);

    $this->actingAs(User::factory()->create(['timezone' => 'America/New_York']));
    expect($event->description())->toContain('2:30 PM');

    $this->actingAs(User::factory()->create(['timezone' => null]));
    expect($event->description())->toContain('6:30 PM');
});

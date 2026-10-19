<?php

use App\Models\User;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('guests are redirected to the login page', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

test('the old dashboard address redirects to the ticket list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/tickets');
});

<?php

use App\Models\Ticket;
use Illuminate\Database\LazyLoadingViolationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('lazy loading a relation throws outside production', function () {
    Ticket::factory()->count(2)->create();

    Ticket::query()->get()->first()->requester;
})->throws(LazyLoadingViolationException::class);

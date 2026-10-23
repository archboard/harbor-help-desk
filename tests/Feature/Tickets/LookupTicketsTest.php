<?php

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->url = 'http://acme.harbor.test/tickets/lookup';
});

test('tickets are found by number or part of the subject', function () {
    $refund = Ticket::factory()->for($this->desk)->create(['subject' => 'Refund for my order']);
    Ticket::factory()->for($this->desk)->create(['subject' => 'Something else']);
    $this->actingAs($this->agent);

    $this->getJson("{$this->url}?search=refund fo")->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $refund->id);
    $this->getJson("{$this->url}?search=%23{$refund->number}")->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $refund->id);
    $this->getJson("{$this->url}?search=%25")->assertOk()->assertJsonCount(0);
    $this->getJson($this->url)->assertOk()->assertJsonCount(0);
});

test('at most ten tickets come back', function () {
    Ticket::factory()->for($this->desk)->count(12)->create(['subject' => 'Printer jam']);
    $this->actingAs($this->agent);

    $this->getJson("{$this->url}?search=printer")->assertOk()->assertJsonCount(10);
});

test('tickets on desks the user can not see are left out', function () {
    $hidden = Ticket::factory()->for(Desk::factory()->create())->create(['subject' => 'Hidden budget']);
    $this->actingAs($this->agent);

    $this->getJson("{$this->url}?search=budget")->assertOk()->assertJsonCount(0);
    $this->getJson("{$this->url}?search={$hidden->number}")->assertOk()->assertJsonCount(0);
});

test('another workspace\'s tickets are never found', function () {
    $foreign = Workspace::factory()->create(['subdomain' => 'other'])->execute(fn () => Ticket::factory()->create(['subject' => 'B private subject']));
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent);

    $this->getJson("{$this->url}?search=private")->assertOk()->assertJsonCount(0);
    $this->getJson("{$this->url}?search={$foreign->number}")->assertOk()->assertJsonMissing(['id' => $foreign->id]);
});

test('guests are turned away', function () {
    $this->getJson("{$this->url}?search=a")->assertUnauthorized();
});

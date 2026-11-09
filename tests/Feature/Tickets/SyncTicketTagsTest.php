<?php

use App\Actions\Tickets\SyncTicketTags;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('tags are reused ignoring case, created when new, and every change is logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $billing = Tag::factory()->create(['name' => 'Billing']);

    app(SyncTicketTags::class)->handle($agent, $ticket, ['names' => ['billing', ' Urgent ', 'URGENT']]);
    app(SyncTicketTags::class)->handle($agent, $ticket, ['names' => ['Urgent']]);

    expect(Tag::orderBy('name')->pluck('name')->all())->toBe(['Billing', 'Urgent']);
    expect($ticket->tags()->pluck('name')->all())->toBe(['Urgent']);
    expect($ticket->events()->with('actor')->get()->map->description()->all())->toBe([
        'Jane Doe added the tag “Billing”.',
        'Jane Doe added the tag “Urgent”.',
        'Jane Doe removed the tag “Billing”.',
    ]);
    expect($billing->fresh())->not->toBeNull();
});

test('another workspace tag with the same name is not reused', function () {
    $foreignTag = Workspace::factory()->create()->execute(fn () => Tag::factory()->create(['name' => 'billing']));
    $ticket = Ticket::factory()->create();

    app(SyncTicketTags::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, ['names' => ['billing']]);

    expect($ticket->tags()->sole()->id)->not->toBe($foreignTag->id);
});

test('agents off the desk cannot tag its tickets', function () {
    $ticket = Ticket::factory()->create();
    $outsider = deskMember($this->workspace, Ticket::factory()->create()->desk);

    expect(fn () => app(SyncTicketTags::class)->handle($outsider, $ticket, ['names' => ['billing']]))
        ->toThrow(AuthorizationException::class);
});

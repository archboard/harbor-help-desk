<?php

use App\Actions\Tickets\SyncTicketTags;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

function ticketAgent(Workspace $workspace, Ticket $ticket): User
{
    $user = User::factory()->create(['name' => 'Jane Doe']);
    $workspace->users()->attach($user, ['role' => WorkspaceRole::AGENT]);
    $ticket->desk->users()->attach($user, ['role' => DeskRole::AGENT, 'workspace_id' => $workspace->id]);

    return $user;
}

test('tags are reused ignoring case, created when new, and every change is logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = ticketAgent($this->workspace, $ticket);
    $billing = Tag::factory()->create(['name' => 'Billing']);

    app(SyncTicketTags::class)->handle($agent, $ticket, ['names' => ['billing', ' Urgent ', 'URGENT']]);
    app(SyncTicketTags::class)->handle($agent, $ticket, ['names' => ['Urgent']]);

    expect(Tag::orderBy('name')->pluck('name')->all())->toBe(['Billing', 'Urgent']);
    expect($ticket->tags()->pluck('name')->all())->toBe(['Urgent']);
    expect($ticket->events()->get()->map->description()->all())->toBe([
        'Jane Doe added the tag “Billing”.',
        'Jane Doe added the tag “Urgent”.',
        'Jane Doe removed the tag “Billing”.',
    ]);
    expect($billing->fresh())->not->toBeNull();
});

test('another workspace tag with the same name is not reused', function () {
    $foreignTag = Workspace::factory()->create()->execute(fn () => Tag::factory()->create(['name' => 'billing']));
    $ticket = Ticket::factory()->create();

    app(SyncTicketTags::class)->handle(ticketAgent($this->workspace, $ticket), $ticket, ['names' => ['billing']]);

    expect($ticket->tags()->sole()->id)->not->toBe($foreignTag->id);
});

test('agents off the desk cannot tag its tickets', function () {
    $ticket = Ticket::factory()->create();
    $outsider = ticketAgent($this->workspace, Ticket::factory()->create());

    expect(fn () => app(SyncTicketTags::class)->handle($outsider, $ticket, ['names' => ['billing']]))
        ->toThrow(AuthorizationException::class);
});

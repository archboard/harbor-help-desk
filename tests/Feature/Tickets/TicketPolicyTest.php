<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * Creates the ticket's desk and one user per role around it.
 *
 * @return array{ticket: Ticket, users: array<string, User>}
 */
function ticketWithRoles(Workspace $workspace): array
{
    $ticket = Ticket::factory()->create();
    $users = [];

    foreach (['admin' => WorkspaceRole::ADMIN, 'lead' => DeskRole::LEAD, 'agent' => DeskRole::AGENT, 'bystander' => null, 'outsider' => false] as $who => $role) {
        $users[$who] = User::factory()->create();

        if ($role !== false) {
            $workspace->users()->attach($users[$who], ['role' => $role === WorkspaceRole::ADMIN ? WorkspaceRole::ADMIN : WorkspaceRole::AGENT]);
        }

        if ($role instanceof DeskRole) {
            $ticket->desk->users()->attach($users[$who], ['role' => $role, 'workspace_id' => $workspace->id]);
        }
    }

    return ['ticket' => $ticket, 'users' => $users];
}

test('abilities follow access to the ticket desk', function (string $ability, array $allowed) {
    ['ticket' => $ticket, 'users' => $users] = ticketWithRoles($this->workspace);

    foreach ($users as $who => $user) {
        $arguments = match ($ability) {
            'viewAny' => Ticket::class,
            'create' => [Ticket::class, $ticket->desk],
            default => $ticket,
        };

        expect($user->can($ability, $arguments))->toBe(in_array($who, $allowed), "{$who} {$ability}");
    }
})->with([
    'viewAny' => ['viewAny', ['admin', 'lead', 'agent', 'bystander']],
    'view' => ['view', ['admin', 'lead', 'agent']],
    'create' => ['create', ['admin', 'lead', 'agent']],
    'update' => ['update', ['admin', 'lead', 'agent']],
    'delete' => ['delete', ['admin', 'lead']],
    'restore' => ['restore', ['admin', 'lead']],
]);

test('agents see tickets on their desks only, including archived desks', function () {
    ['ticket' => $ticket, 'users' => $users] = ticketWithRoles($this->workspace);
    Ticket::factory()->create();
    $ticket->desk->delete();

    expect(Ticket::visibleTo($users['agent'])->pluck('id')->all())->toBe([$ticket->id]);
    expect(Ticket::visibleTo($users['admin'])->count())->toBe(2);
    expect(Ticket::visibleTo($users['bystander'])->count())->toBe(0);
    expect($users['agent']->can('view', $ticket->fresh()))->toBeTrue();
});

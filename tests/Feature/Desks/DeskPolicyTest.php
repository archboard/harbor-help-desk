<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::factory()->create();

    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::Admin]);

    $this->lead = User::factory()->create();
    $this->workspace->users()->attach($this->lead, ['role' => WorkspaceRole::Agent]);
    $this->desk->users()->attach($this->lead, ['role' => DeskRole::Lead, 'workspace_id' => $this->workspace->id]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::Agent]);
    $this->desk->users()->attach($this->agent, ['role' => DeskRole::Agent, 'workspace_id' => $this->workspace->id]);

    $this->bystander = User::factory()->create();
    $this->workspace->users()->attach($this->bystander, ['role' => WorkspaceRole::Agent]);

    $this->outsider = User::factory()->create();
});

test('abilities follow workspace and desk roles', function (string $ability, array $allowed) {
    foreach (['admin', 'lead', 'agent', 'bystander', 'outsider'] as $who) {
        $arguments = in_array($ability, ['viewAny', 'create']) ? Desk::class : $this->desk;

        expect($this->{$who}->can($ability, $arguments))
            ->toBe(in_array($who, $allowed), "{$who} {$ability}");
    }
})->with([
    'viewAny' => ['viewAny', ['admin', 'lead', 'agent', 'bystander']],
    'view' => ['view', ['admin', 'lead', 'agent']],
    'create' => ['create', ['admin']],
    'update' => ['update', ['admin', 'lead']],
    'manageMembers' => ['manageMembers', ['admin', 'lead']],
    'delete' => ['delete', ['admin']],
    'restore' => ['restore', ['admin']],
]);

test('admins see every desk and agents only their own', function () {
    expect(Desk::visibleTo($this->admin)->count())->toBe(2);
    expect(Desk::visibleTo($this->agent)->pluck('id')->all())->toBe([$this->desk->id]);
    expect(Desk::visibleTo($this->bystander)->count())->toBe(0);
});

test('admins of another workspace have no say over this one', function () {
    Workspace::factory()->create()->users()->attach($this->outsider, ['role' => WorkspaceRole::Owner]);

    expect($this->outsider->can('update', $this->desk))->toBeFalse();
    expect(Desk::visibleTo($this->outsider)->count())->toBe(0);
});

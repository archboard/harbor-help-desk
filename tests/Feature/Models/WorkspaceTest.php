<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

test('agents join workspaces with a role on the pivot', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()
        ->hasAttached($user, ['role' => WorkspaceRole::Admin->value])
        ->create();

    expect($user->workspaces->sole()->pivot->role)->toBe(WorkspaceRole::Admin);
    expect($workspace->users->sole()->is($user))->toBeTrue();
});

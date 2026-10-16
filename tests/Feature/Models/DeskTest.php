<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('a new workspace gets one default desk', function () {
    $workspace = Workspace::factory()->create(['name' => 'Springfield District'])->makeCurrent();

    expect(Desk::sole())
        ->name->toBe('Springfield District')
        ->slug->toBe('default')
        ->workspace_id->toBe($workspace->id);
});

test('workspace b cannot read workspace a desks', function () {
    $deskA = Workspace::factory()->create()->execute(fn () => Desk::factory()->create());
    Workspace::factory()->create()->makeCurrent();

    expect(Desk::find($deskA->id))->toBeNull();
    expect(Desk::pluck('slug')->all())->toBe(['default']);
});

test('slugs are unique among active desks only', function () {
    Workspace::factory()->create()->makeCurrent();
    $desk = Desk::factory()->create(['slug' => 'north']);

    expect(fn () => DB::transaction(fn () => Desk::factory()->create(['slug' => 'north'])))
        ->toThrow(UniqueConstraintViolationException::class);

    $desk->delete();

    expect(Desk::factory()->create(['slug' => 'north'])->slug)->toBe('north');
    expect($desk->fresh()->archived_at)->not->toBeNull();
});

test('users expose their workspace and desk roles', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->hasAttached($user, ['role' => WorkspaceRole::Admin->value])->create()->makeCurrent();
    $desk = Desk::factory()->create();
    $desk->users()->attach($user, ['role' => DeskRole::Lead->value, 'workspace_id' => $workspace->id]);

    expect($user->workspaceRole($workspace))->toBe(WorkspaceRole::Admin);
    expect($user->deskRole($desk))->toBe(DeskRole::Lead);
    expect($user->deskRole(Desk::where('slug', 'default')->sole()))->toBeNull();
    expect($user->workspaceRole(Workspace::factory()->create()))->toBeNull();
});

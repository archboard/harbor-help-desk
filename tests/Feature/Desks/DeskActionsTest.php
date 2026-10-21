<?php

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\SetDeskMember;
use App\Actions\Desks\UpdateDesk;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('creating a desk defaults the slug from the name', function () {
    $desk = app(CreateDesk::class)->handle($this->admin, ['name' => 'North High']);

    expect($desk)
        ->workspace_id->toBe($this->workspace->id)
        ->slug->toBe('north-high');
});

test('desk slugs must be unique among active desks', function () {
    expect(fn () => app(CreateDesk::class)->handle($this->admin, ['name' => 'Other', 'slug' => 'default']))
        ->toThrow(ValidationException::class);
});

test('agents cannot create desks', function () {
    expect(fn () => app(CreateDesk::class)->handle($this->agent, ['name' => 'North High']))
        ->toThrow(AuthorizationException::class);
});

test('desk leads can update their desk', function () {
    $desk = Desk::factory()->create();
    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::LEAD->value]);

    app(UpdateDesk::class)->handle($this->agent, $desk, ['name' => 'Renamed']);

    expect($desk->fresh()->name)->toBe('Renamed');
});

test('the last active desk cannot be archived', function () {
    $default = Desk::sole();

    expect(fn () => app(ArchiveDesk::class)->handle($this->admin, $default))
        ->toThrow(ValidationException::class);

    expect($default->fresh()->trashed())->toBeFalse();
});

test('archived desks can be restored unless their slug was taken', function () {
    $desk = Desk::factory()->create(['slug' => 'north']);
    app(ArchiveDesk::class)->handle($this->admin, $desk);

    expect(Desk::count())->toBe(1);

    app(RestoreDesk::class)->handle($this->admin, $desk);
    expect($desk->fresh()->trashed())->toBeFalse();

    app(ArchiveDesk::class)->handle($this->admin, $desk);
    Desk::factory()->create(['slug' => 'north']);

    expect(fn () => app(RestoreDesk::class)->handle($this->admin, $desk))
        ->toThrow(ValidationException::class);
});

test('members can be added, have their role changed and be removed', function () {
    $desk = Desk::sole();

    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::AGENT->value]);
    expect($this->agent->deskRole($desk))->toBe(DeskRole::AGENT);

    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::LEAD->value]);
    expect($this->agent->deskRole($desk))->toBe(DeskRole::LEAD);
    $this->assertDatabaseHas('desk_user', ['desk_id' => $desk->id, 'workspace_id' => $this->workspace->id]);

    app(RemoveDeskMember::class)->handle($this->admin, $desk, $this->agent);
    expect($this->agent->deskRole($desk))->toBeNull();
});

test('only workspace members can be added to a desk', function () {
    $stranger = User::factory()->create();
    Workspace::factory()->create()->users()->attach($stranger, ['role' => WorkspaceRole::AGENT]);

    expect(fn () => app(SetDeskMember::class)->handle($this->admin, Desk::sole(), $stranger, ['role' => DeskRole::AGENT->value]))
        ->toThrow(ValidationException::class);
});

test('the rotation flag is only written when given, so a role change keeps it', function () {
    $desk = Desk::sole();
    $pivot = fn () => $desk->users()->whereKey($this->agent->id)->first()->pivot;

    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::AGENT->value]);
    expect($pivot()->in_rotation)->toBeTrue();

    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::AGENT->value, 'in_rotation' => false]);
    expect($pivot()->in_rotation)->toBeFalse();

    app(SetDeskMember::class)->handle($this->admin, $desk, $this->agent, ['role' => DeskRole::LEAD->value]);
    expect($pivot()->in_rotation)->toBeFalse();
});

test('setting the rotation flag needs the manageMembers ability', function () {
    $desk = Desk::sole();
    $desk->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);

    expect(fn () => app(SetDeskMember::class)->handle($this->agent, $desk, $this->agent, ['role' => DeskRole::AGENT->value, 'in_rotation' => false]))
        ->toThrow(AuthorizationException::class);
});

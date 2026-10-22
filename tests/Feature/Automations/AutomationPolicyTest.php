<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::sole();
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
    $this->otherDesk = Desk::factory()->create();
    $this->otherLead = deskMember($this->workspace, $this->otherDesk, DeskRole::LEAD);
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

test('admins manage every automation', function () {
    foreach ([Automation::factory()->create(), Automation::factory()->forDesk($this->desk)->create()] as $automation) {
        expect($this->admin->can('view', $automation))->toBeTrue()
            ->and($this->admin->can('update', $automation))->toBeTrue()
            ->and($this->admin->can('delete', $automation))->toBeTrue();
    }

    expect($this->admin->can('viewAny', Automation::class))->toBeTrue();
});

test('a desk lead manages only the automations for their desk', function () {
    $workspaceWide = Automation::factory()->create();
    $own = Automation::factory()->forDesk($this->desk)->create();

    expect($this->lead->can('viewAny', Automation::class))->toBeTrue()
        ->and($this->lead->can('view', $own))->toBeTrue()
        ->and($this->lead->can('update', $own))->toBeTrue()
        ->and($this->lead->can('delete', $own))->toBeTrue()
        ->and($this->lead->can('create', Automation::factory()->forDesk($this->desk)->make()))->toBeTrue()
        ->and($this->otherLead->can('view', $own))->toBeFalse()
        ->and($this->otherLead->can('update', $own))->toBeFalse()
        ->and($this->lead->can('view', $workspaceWide))->toBeFalse()
        ->and($this->lead->can('create', Automation::factory()->make()))->toBeFalse();
});

test('agents cannot see automations', function () {
    $automation = Automation::factory()->forDesk($this->desk)->create();

    expect($this->agent->can('viewAny', Automation::class))->toBeFalse()
        ->and($this->agent->can('view', $automation))->toBeFalse()
        ->and($this->agent->can('update', $automation))->toBeFalse();
});

test('for a ticket returns workspace-wide automations and those of its desk', function () {
    $wide = Automation::factory()->create();
    $own = Automation::factory()->forDesk($this->desk)->create();
    Automation::factory()->forDesk($this->otherDesk)->create();
    $ticket = Ticket::factory()->create(['desk_id' => $this->desk->id]);

    expect(Automation::for($ticket)->pluck('id')->sort()->values()->all())->toBe(collect([$wide->id, $own->id])->sort()->values()->all());
});

test('automations are scoped to the current workspace', function () {
    $mine = Automation::factory()->create();
    Workspace::factory()->create()->execute(fn () => Automation::factory()->create());

    expect(Automation::pluck('id')->all())->toBe([$mine->id]);
});

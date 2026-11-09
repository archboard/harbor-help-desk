<?php

use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
});

test('the palette prop is absent unless requested', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('palette'));
});

test("the palette holds only this workspace's agents and fields, and the macros this user may see", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    Workspace::factory()->create()->execute(function () {
        Macro::factory()->create(['name' => 'Foreign']);
        CustomField::factory()->select()->create(['name' => 'Foreign field']);
    });
    $this->workspace->makeCurrent();

    $shared = Macro::factory()->create(['name' => 'B shared']);
    $mine = Macro::factory()->personal($this->agent)->create(['name' => 'A mine']);
    Macro::factory()->personal(User::factory()->create())->create();
    Macro::factory()->forDesk(Desk::factory()->create())->create();

    $field = CustomField::factory()->select()->create();
    $field->desks()->attach($this->desk, ['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('palette', fn (Assert $reload) => $reload
            ->has('palette.agents', 1)
            ->where('palette.agents.0.id', $this->agent->id)
            ->has('palette.macros', 2)
            ->whereAll(['palette.macros.0.id' => $mine->id, 'palette.macros.1.id' => $shared->id])
            ->has('palette.desks', 1)
            ->has('palette.desks.0.custom_fields', 1)
            ->where('palette.desks.0.custom_fields.0.id', $field->id)));
});

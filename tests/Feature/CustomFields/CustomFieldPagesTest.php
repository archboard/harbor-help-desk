<?php

use App\Enums\WorkspaceRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('admins see the fields, the desks using them and the types', function () {
    $field = CustomField::factory()->create(['name' => 'Order', 'type' => 'text']);
    $field->desks()->attach(Desk::sole(), ['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->get(route('custom-fields.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/CustomFields')
            ->where('fields.0.name', 'Order')
            ->where('fields.0.type_label', 'Text')
            ->where('fields.0.desks.0.id', Desk::sole()->id)
            ->where('types.multi_select', 'Multi-select'));
});

test('admins create, update and delete fields through the web routes', function () {
    $this->actingAs($this->admin);

    $this->post(route('custom-fields.store'), ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'L']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $field = CustomField::sole();
    expect($field->options)->toBe(['S', 'L']);

    $this->patch(route('custom-fields.update', $field), ['name' => 'T-shirt size', 'is_required' => true])
        ->assertSessionHasNoErrors();
    expect($field->fresh())->name->toBe('T-shirt size')->is_required->toBeTrue();

    $this->delete(route('custom-fields.destroy', $field))->assertRedirect();
    expect(CustomField::count())->toBe(0);
});

test('validation errors come back to the form', function () {
    $this->actingAs($this->admin)
        ->post(route('custom-fields.store'), ['type' => 'select'])
        ->assertSessionHasErrors(['name', 'options']);
});

test('agents cannot manage fields', function () {
    $field = CustomField::factory()->create(['name' => 'Order']);
    $this->actingAs($this->agent);

    $this->get(route('custom-fields.index'))->assertForbidden();
    $this->post(route('custom-fields.store'), ['name' => 'Size', 'type' => 'text'])->assertForbidden();
    $this->patch(route('custom-fields.update', $field), ['name' => 'Renamed'])->assertForbidden();
    $this->delete(route('custom-fields.destroy', $field))->assertForbidden();

    expect($field->fresh()->name)->toBe('Order');
});

test("another workspace's fields are not reachable", function () {
    $other = Workspace::factory()->create();
    $foreign = $other->execute(fn () => CustomField::factory()->create(['name' => 'Foreign']));
    $this->workspace->makeCurrent();

    $this->actingAs($this->admin)
        ->get(route('custom-fields.index'))
        ->assertInertia(fn (Assert $page) => $page->has('fields', 0));

    $this->actingAs($this->admin)->patch(route('custom-fields.update', $foreign->id), ['name' => 'Hijacked'])->assertNotFound();
    expect($foreign->fresh()->name)->toBe('Foreign');
});

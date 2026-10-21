<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::sole();

    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('admins see every desk, archived ones and the create form', function () {
    $archived = Desk::factory()->create();
    $archived->delete();

    $this->actingAs($this->admin)
        ->get(route('desks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('desks/Index')
            ->has('desks', 1)
            ->where('archivedDesks.0.id', $archived->id)
            ->where('canManageDesks', true));
});

test('agents only see their own desks', function () {
    $other = Desk::factory()->create();
    $other->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->get(route('desks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks', [['id' => $other->id, 'name' => $other->name, 'slug' => $other->slug, 'assignment_strategy' => 'manual', 'sla_policy_id' => null, 'archived_at' => null]])
            ->where('archivedDesks', [])
            ->where('canManageDesks', false));

    $this->actingAs($this->agent)->get(route('desks.show', $this->desk))->assertForbidden();
});

test('the desk page lists members, candidates and abilities', function () {
    $this->desk->users()->attach($this->agent, ['role' => DeskRole::LEAD, 'workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->get(route('desks.show', $this->desk))
        ->assertInertia(fn (Assert $page) => $page
            ->component('desks/Show')
            ->where('desk', [
                'id' => $this->desk->id,
                'name' => $this->desk->name,
                'slug' => $this->desk->slug,
                'assignment_strategy' => 'manual',
                'sla_policy_id' => null,
                'custom_fields' => [],
                'archived_at' => null,
            ])
            ->where('members', [['id' => $this->agent->id, 'name' => $this->agent->name, 'email' => $this->agent->email, 'role' => 'lead', 'in_rotation' => true]])
            ->where('roles', ['lead' => 'Lead', 'agent' => 'Agent'])
            ->where('candidates', [['id' => $this->admin->id, 'name' => $this->admin->name, 'email' => $this->admin->email]])
            ->where('can', ['update' => true, 'manageMembers' => true, 'archive' => true]));
});

test('desks and members are managed through the web routes', function () {
    $this->actingAs($this->admin);

    $this->post(route('desks.store'), ['name' => 'North High'])->assertRedirect();
    $desk = Desk::where('slug', 'north-high')->sole();

    $this->put(route('desks.members.update', [$desk, $this->agent]), ['role' => 'agent'])->assertRedirect();
    expect($this->agent->deskRole($desk))->toBe(DeskRole::AGENT);

    $this->delete(route('desks.destroy', $desk))->assertRedirect(route('desks.index'));
    expect($desk->fresh()->trashed())->toBeTrue();

    $this->delete(route('desks.destroy', $this->desk))->assertSessionHasErrors('desk');
});

test('the desk page lists every field and the ones the desk uses', function () {
    $enabled = CustomField::factory()->create(['position' => 0]);
    $other = CustomField::factory()->create(['position' => 1]);
    $this->desk->customFields()->attach($enabled, ['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->admin)
        ->get(route('desks.show', $this->desk))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customFields', 2)
            ->where('customFields.0.id', $enabled->id)
            ->where('customFields.1.id', $other->id)
            ->has('desk.custom_fields', 1)
            ->where('desk.custom_fields.0.id', $enabled->id));
});

test('desk leads choose the desk\'s fields and agents cannot', function () {
    $field = CustomField::factory()->create();
    $this->desk->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $lead = User::factory()->create();
    $this->workspace->users()->attach($lead, ['role' => WorkspaceRole::AGENT]);
    $this->desk->users()->attach($lead, ['role' => DeskRole::LEAD, 'workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)
        ->put(route('desks.custom-fields.update', $this->desk), ['field_ids' => [$field->id]])
        ->assertForbidden();
    expect($this->desk->customFields()->count())->toBe(0);

    $this->actingAs($lead)
        ->put(route('desks.custom-fields.update', $this->desk), ['field_ids' => [$field->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($this->desk->customFields()->pluck('custom_fields.id')->all())->toBe([$field->id]);

    $this->actingAs($lead)
        ->put(route('desks.custom-fields.update', $this->desk), ['field_ids' => []])
        ->assertSessionHasNoErrors();
    expect($this->desk->customFields()->count())->toBe(0);
});

<?php

use App\Enums\DeskRole;
use App\Enums\MacroScope;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->otherDesk = Desk::factory()->create();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

test('an agent can create personal macros but not shared ones', function () {
    $this->actingAs($this->agent);

    $this->post(route('macros.store'), ['name' => 'Thanks', 'scope' => 'personal', 'body' => 'Thanks {{ requester.first_name }}'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('macros.store'), ['name' => 'Shared', 'scope' => 'workspace', 'body' => 'Hi'])->assertForbidden();
    $this->post(route('macros.store'), ['name' => 'Desk', 'scope' => 'desk', 'desk_id' => $this->desk->id, 'body' => 'Hi'])->assertForbidden();

    $macro = Macro::sole();
    expect($macro->user_id)->toBe($this->agent->id)
        ->and($macro->created_by)->toBe($this->agent->id)
        ->and($macro->desk_id)->toBeNull()
        ->and($macro->kind())->toBe(MacroScope::PERSONAL);
});

test('a lead manages macros for their own desk only', function () {
    $this->actingAs($this->lead);

    $this->post(route('macros.store'), ['name' => 'Desk', 'scope' => 'desk', 'desk_id' => $this->desk->id, 'body' => 'Hi'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('macros.store'), ['name' => 'Other', 'scope' => 'desk', 'desk_id' => $this->otherDesk->id, 'body' => 'Hi'])->assertForbidden();
    $this->post(route('macros.store'), ['name' => 'Wide', 'scope' => 'workspace', 'body' => 'Hi'])->assertForbidden();

    $macro = Macro::sole();
    expect($macro->desk_id)->toBe($this->desk->id)->and($macro->user_id)->toBeNull();

    $this->patch(route('macros.update', $macro), ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($macro->fresh()->name)->toBe('Renamed');

    $this->actingAs($this->agent)->delete(route('macros.destroy', $macro))->assertForbidden();
    $this->actingAs($this->lead)->delete(route('macros.destroy', $macro))->assertRedirect();
    expect(Macro::count())->toBe(0);
});

test('admins manage workspace-wide macros and any desk\'s', function () {
    $this->actingAs($this->admin);

    $this->post(route('macros.store'), ['name' => 'Wide', 'scope' => 'workspace', 'body' => 'Hi'])->assertSessionHasNoErrors();
    $this->post(route('macros.store'), ['name' => 'Other', 'scope' => 'desk', 'desk_id' => $this->otherDesk->id, 'body' => 'Hi'])->assertSessionHasNoErrors();

    expect(Macro::count())->toBe(2);
});

test('personal macros are invisible to everyone else, admins included', function () {
    $mine = Macro::factory()->personal($this->agent)->create();
    $shared = Macro::factory()->create();
    $deskMacro = Macro::factory()->forDesk($this->desk)->create();
    $otherDeskMacro = Macro::factory()->forDesk($this->otherDesk)->create();

    expect(Macro::visibleTo($this->agent)->pluck('id')->all())->toEqualCanonicalizing([$mine->id, $shared->id, $deskMacro->id])
        ->and(Macro::visibleTo($this->lead)->pluck('id')->all())->toEqualCanonicalizing([$shared->id, $deskMacro->id])
        ->and(Macro::visibleTo($this->admin)->pluck('id')->all())->toEqualCanonicalizing([$shared->id, $deskMacro->id, $otherDeskMacro->id]);

    $this->actingAs($this->lead);
    $this->patch(route('macros.update', $mine), ['name' => 'Mine now'])->assertForbidden();
    $this->actingAs($this->admin);
    $this->delete(route('macros.destroy', $mine))->assertForbidden();
    expect($mine->fresh()->name)->not->toBe('Mine now');
});

test('usableOn limits macros to workspace-wide ones and the ticket\'s desk', function () {
    $shared = Macro::factory()->create();
    $onDesk = Macro::factory()->forDesk($this->desk)->create();
    Macro::factory()->forDesk($this->otherDesk)->create();
    $ticket = Ticket::factory()->create(['desk_id' => $this->desk->id]);

    expect(Macro::usableOn($ticket)->pluck('id')->all())->toEqualCanonicalizing([$shared->id, $onDesk->id]);
});

test('the settings page lists visible macros and what the user may manage', function () {
    $mine = Macro::factory()->personal($this->agent)->create();
    $shared = Macro::factory()->create();

    $this->actingAs($this->agent)
        ->get(route('macros.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Macros')
            ->has('macros', 2)
            ->where('editableIds', [$mine->id])
            ->where('canCreateWorkspaceMacro', false)
            ->where('creatableDeskIds', []));

    $this->actingAs($this->lead)
        ->get(route('macros.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('editableIds', [])
            ->where('creatableDeskIds', [$this->desk->id]));

    expect($shared->exists)->toBeTrue();
});

test('actions are checked against a fixed schema', function () {
    $this->actingAs($this->agent);
    $post = fn (array $actions) => $this->post(route('macros.store'), ['name' => 'M', 'scope' => 'personal', 'actions' => $actions]);

    $post(['status' => 'nope'])->assertSessionHasErrors('actions.status');
    $post(['priority' => 'nope'])->assertSessionHasErrors('actions.priority');
    $post(['add_tags' => 'vip'])->assertSessionHasErrors('actions.add_tags');
    $post(['remove_tags' => [['x']]])->assertSessionHasErrors('actions.remove_tags.0');
    $post(['assign_to_me' => 'maybe'])->assertSessionHasErrors('actions.assign_to_me');
    $post(['close_account' => true])->assertSessionHasErrors('actions');
    expect(Macro::count())->toBe(0);

    $post(['status' => 'solved', 'priority' => 'high', 'add_tags' => ['vip'], 'remove_tags' => ['spam'], 'assign_to_me' => true])
        ->assertSessionHasNoErrors();
    expect(Macro::sole()->actions)->toEqual(['status' => 'solved', 'priority' => 'high', 'add_tags' => ['vip'], 'remove_tags' => ['spam'], 'assign_to_me' => true]);
});

test('a macro needs a body or actions, and a desk scope needs a desk of this workspace', function () {
    $this->actingAs($this->admin);

    $this->post(route('macros.store'), ['name' => 'Empty', 'scope' => 'personal'])->assertSessionHasErrors(['body', 'actions']);
    $this->post(route('macros.store'), ['name' => 'Empty', 'scope' => 'personal', 'body' => '', 'actions' => []])->assertSessionHasErrors(['body', 'actions']);
    $this->post(route('macros.store'), ['name' => 'No desk', 'scope' => 'desk', 'body' => 'Hi'])->assertSessionHasErrors('desk_id');
    $this->post(route('macros.store'), ['name' => 'Desk', 'scope' => 'workspace', 'desk_id' => $this->desk->id, 'body' => 'Hi'])->assertSessionHasErrors('desk_id');
    $this->post(route('macros.store'), ['scope' => 'galaxy', 'body' => 'Hi'])->assertSessionHasErrors(['name', 'scope']);

    $macro = Macro::factory()->personal($this->admin)->create(['body' => 'Hi', 'actions' => []]);
    $this->patch(route('macros.update', $macro), ['body' => null])->assertSessionHasErrors('body');
    $this->patch(route('macros.update', $macro), ['body' => null, 'actions' => ['assign_to_me' => true]])->assertSessionHasNoErrors();
    expect($macro->fresh())->body->toBeNull()->actions->toBe(['assign_to_me' => true]);
});

test('the API lists, shows, updates and deletes macros', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    actAs($this->agent, $this->workspace);
    $mine = Macro::factory()->personal($this->agent)->create(['name' => 'Mine', 'body' => 'Hi', 'actions' => ['status' => 'pending']]);
    $theirs = Macro::factory()->personal($this->lead)->create();
    $base = 'http://acme.harbor.test/api/v1/macros';

    $this->getJson($base)->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.id', $mine->id)
        ->assertJsonPath('0.scope', 'personal')
        ->assertJsonPath('0.actions.status', 'pending')
        ->assertJsonPath('0.actions.add_tags', []);
    $this->getJson("$base/{$theirs->id}")->assertForbidden();

    $id = $this->postJson($base, ['name' => 'New', 'scope' => 'personal', 'body' => 'Yo'])->assertCreated()->json('id');
    $this->putJson("$base/$id", ['body_is_internal' => true])->assertOk()->assertJsonPath('body_is_internal', true);
    $this->deleteJson("$base/$id")->assertNoContent();
    $this->postJson($base, ['name' => 'Wide', 'scope' => 'workspace', 'body' => 'Yo'])->assertForbidden();
});

test('another workspace\'s macros can be neither read nor changed', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $foreign = $other->execute(fn () => Macro::factory()->create(['name' => 'Foreign']));
    $base = 'http://acme.harbor.test/api/v1/macros';

    actAs($this->admin, $this->workspace);
    $this->getJson($base)->assertOk()->assertJsonCount(0);
    $this->getJson("$base/{$foreign->id}")->assertNotFound();
    $this->putJson("$base/{$foreign->id}", ['name' => 'Hacked'])->assertNotFound();
    $this->deleteJson("$base/{$foreign->id}")->assertNotFound();

    $this->postJson($base, ['name' => 'Cross', 'scope' => 'desk', 'desk_id' => $other->execute(fn () => Desk::sole()->id), 'body' => 'Hi'])
        ->assertUnprocessable()->assertJsonValidationErrors('desk_id');

    expect($foreign->fresh()->name)->toBe('Foreign');
});

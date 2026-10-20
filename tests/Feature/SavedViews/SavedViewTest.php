<?php

use App\Enums\WorkspaceRole;
use App\Models\SavedView;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->colleague = deskMember($this->workspace, $this->desk, attributes: ['name' => 'Sam Rivera']);
});

test('the ticket list offers the user\'s own views and shared ones, not other people\'s private ones', function () {
    $mine = SavedView::factory()->for($this->agent)->create(['name' => 'Mine']);
    $shared = SavedView::factory()->for($this->colleague)->shared()->create(['name' => 'Shared']);
    SavedView::factory()->for($this->colleague)->create(['name' => 'Private']);

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('savedViews', 2)
            ->where('savedViews.0.id', $mine->id)
            ->where('savedViews.1.id', $shared->id)
            ->where('savedViews.1.owner.name', 'Sam Rivera'));
});

test('views are created, renamed, shared and deleted by their owner', function () {
    $this->actingAs($this->agent);

    $this->post(route('saved-views.store'), [
        'name' => 'Urgent open',
        'filters' => ['status' => ['open', 'pending'], 'priority' => 'urgent', 'sort' => 'due'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $view = SavedView::sole();
    expect($view->user_id)->toBe($this->agent->id)
        ->and($view->filters)->toEqual(['status' => ['open', 'pending'], 'priority' => 'urgent', 'sort' => 'due'])
        ->and($view->is_shared)->toBeFalse();

    $this->patch(route('saved-views.update', $view), ['name' => 'Hot', 'is_shared' => true])->assertSessionHasNoErrors();
    expect($view->fresh())->name->toBe('Hot')->is_shared->toBeTrue();

    $this->delete(route('saved-views.destroy', $view))->assertRedirect();
    expect(SavedView::count())->toBe(0);
});

test('filters must be ones the ticket search understands', function () {
    $this->actingAs($this->agent);

    $this->post(route('saved-views.store'), ['name' => 'Bad', 'filters' => ['status' => 'nope']])
        ->assertSessionHasErrors('filters.status');
    $this->post(route('saved-views.store'), ['name' => 'Bad', 'filters' => ['colour' => 'red']])
        ->assertSessionHasErrors('filters');
    $this->post(route('saved-views.store'), ['filters' => []])
        ->assertSessionHasErrors('name');

    expect(SavedView::count())->toBe(0);
});

test('other people can\'t change a view, except admins tidying shared ones, and only the owner shares', function () {
    $private = SavedView::factory()->for($this->colleague)->create();
    $shared = SavedView::factory()->for($this->colleague)->shared()->create();

    $this->actingAs($this->agent);
    $this->patch(route('saved-views.update', $private), ['name' => 'Taken'])->assertForbidden();
    $this->patch(route('saved-views.update', $shared), ['name' => 'Taken'])->assertForbidden();
    $this->delete(route('saved-views.destroy', $shared))->assertForbidden();

    $admin = User::factory()->create();
    $this->workspace->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);
    $this->actingAs($admin);

    $this->delete(route('saved-views.destroy', $private))->assertForbidden();
    $this->patch(route('saved-views.update', $shared), ['is_shared' => false])->assertForbidden();
    $this->patch(route('saved-views.update', $shared), ['name' => 'Tidied'])->assertSessionHasNoErrors();
    $this->delete(route('saved-views.destroy', $shared))->assertRedirect();

    expect(SavedView::pluck('id')->all())->toBe([$private->id]);
});

test('the API lists, creates, shows, updates and deletes views', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    actAs($this->agent, $this->workspace);
    $private = SavedView::factory()->for($this->colleague)->create();

    $this->postJson('http://acme.harbor.test/api/v1/saved-views', ['name' => 'Mine', 'filters' => ['assignee_id' => 'me']])
        ->assertCreated()
        ->assertJsonPath('filters.assignee_id', 'me');
    $view = SavedView::where('name', 'Mine')->sole();

    $this->getJson('http://acme.harbor.test/api/v1/saved-views')
        ->assertOk()
        ->assertExactJson([[
            'id' => $view->id,
            'user_id' => $this->agent->id,
            'owner' => ['id' => $this->agent->id, 'name' => $this->agent->name, 'email' => $this->agent->email],
            'name' => 'Mine',
            'filters' => ['assignee_id' => 'me'],
            'is_shared' => false,
            'created_at' => $view->created_at->toIso8601String(),
            'updated_at' => $view->updated_at->toIso8601String(),
        ]]);
    $this->getJson("http://acme.harbor.test/api/v1/saved-views/{$private->id}")->assertForbidden();

    $this->patchJson("http://acme.harbor.test/api/v1/saved-views/{$view->id}", ['filters' => []])
        ->assertOk()
        ->assertSee('"filters":{}', false);
    $this->deleteJson("http://acme.harbor.test/api/v1/saved-views/{$view->id}")->assertNoContent();
});

test('another workspace\'s views are out of reach', function () {
    $foreign = Workspace::factory()->create(['subdomain' => 'other'])
        ->execute(fn () => SavedView::factory()->shared()->create());
    $this->workspace->makeCurrent();

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->has('savedViews', 0));
    $this->patch(route('saved-views.update', $foreign->id), ['name' => 'Mine now'])->assertNotFound();
    $this->delete(route('saved-views.destroy', $foreign->id))->assertNotFound();
});

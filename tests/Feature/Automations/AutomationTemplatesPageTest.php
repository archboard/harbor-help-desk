<?php

use App\Automations\Templates;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->web = 'http://acme.harbor.test';

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->deskB = $this->other->execute(fn () => Desk::sole());
});

test('the gallery lists every template for admins and leads and is forbidden for agents', function (string $who) {
    $this->actingAs($this->{$who})->get("{$this->web}/automations/templates")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('automations/Templates')
        ->has('templates', count(Templates::KEYS))
        ->where('templates.0.key', 'auto_close_stale_pending'));

    $this->actingAs(deskMember($this->workspace, $this->desk))->get("{$this->web}/automations/templates")->assertForbidden();
})->with(['admin', 'lead']);

test('a template prefills the builder with its definition for the scope the form starts on', function () {
    $second = Desk::factory()->create(['name' => 'Aaa']);
    Desk::factory()->create(['name' => '000 Archived', 'archived_at' => now()]);

    // A lead is scoped to their desk, so the move goes there.
    $this->actingAs($this->lead)->get("{$this->web}/automations/create?template=route_by_requester_domain")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('automations/Edit')
        ->where('automation', null)
        ->where('template.key', 'route_by_requester_domain')
        ->where('template.name', Templates::find('route_by_requester_domain')->name)
        ->where('template.definition.steps.1.config.desk_id', $this->desk->id));

    // An admin gets the first active desk by name, never an archived one.
    $this->actingAs($this->admin)->get("{$this->web}/automations/create?template=route_by_requester_domain")->assertInertia(fn (Assert $page) => $page
        ->where('template.definition.steps.1.config.desk_id', $second->id));
});

test('a blank builder has no template and an unknown template is a 404', function () {
    $this->actingAs($this->admin)->get("{$this->web}/automations/create")->assertInertia(fn (Assert $page) => $page->where('template', null));
    $this->actingAs($this->admin)->get("{$this->web}/automations/create?template=nope")->assertNotFound();
    $this->actingAs($this->admin)->get("{$this->web}/automations/create?template[]=tag_by_keyword")->assertNotFound();
});

test('saving a prefilled template stays disabled until it is enabled, and never uses another workspace\'s desk', function () {
    $this->actingAs($this->admin)->post("{$this->web}/automations", [
        'name' => 'Route', 'template' => 'route_by_requester_domain',
    ])->assertRedirect();

    expect(Automation::sole()->enabled)->toBeFalse()
        ->and(json_encode(Automation::sole()->definition))->toContain($this->desk->id)->not->toContain($this->deskB->id);
});

test('the gallery and the prefill never resolve another workspace\'s desk', function () {
    $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $content = $this->actingAs($this->admin)->get("{$this->web}/automations/create?template=route_by_requester_domain")->assertOk()->getContent();
    expect($content)->toContain($this->desk->id)->not->toContain($this->deskB->id);

    expect($this->actingAs($this->admin)->get("{$this->web}/automations/templates")->getContent())->not->toContain($this->deskB->id);
});

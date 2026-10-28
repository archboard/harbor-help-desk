<?php

use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    Ticket::factory()->count(2)->create(['desk_id' => $this->desk->id]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->otherDesk = $this->other->execute(function () {
        $desk = Desk::factory()->create();
        Ticket::factory()->count(5)->create(['desk_id' => $desk->id]);

        return $desk;
    });
    $this->workspace->makeCurrent();

    $this->web = 'http://acme.harbor.test/reports';
    $this->api = 'http://acme.harbor.test/api/v1/reports';
});

test('the web page renders a report over the workspace\'s own tickets', function () {
    $this->actingAs($this->agent)
        ->get("{$this->web}/overview")
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Show')
            ->where('report', 'overview')
            ->where('data.created.value', 2)
            ->has('reports.responses'));
});

test('the API returns each report over the workspace\'s own tickets', function () {
    actAs($this->agent, $this->workspace);

    $this->getJson("{$this->api}/overview")->assertOk()->assertJsonPath('created.value', 2);
    $this->getJson("{$this->api}/responses?basis=business")->assertOk()->assertJsonPath('period.basis', 'business')->assertJsonStructure(['median_first_response', 'first_response_within_hour']);
    $this->getJson("{$this->api}/resolution")->assertOk()->assertJsonStructure(['median_resolution', 'reopen_rate', 'trend']);
    $this->getJson("{$this->api}/sla")->assertOk()->assertJsonPath('metrics.0.metric', 'first_response');
    $this->getJson("{$this->api}/satisfaction")->assertOk()->assertJsonStructure(['score', 'response_rate', 'comments']);
});

test('the agents report never lists another workspace\'s agents', function () {
    $this->other->execute(fn () => deskMember($this->other, $this->otherDesk, attributes: ['name' => 'Other Agent']));
    actAs($this->agent, $this->workspace);

    $this->getJson("{$this->api}/agents")->assertOk()->assertJsonCount(1, 'agents')->assertJsonPath('agents.0.agent.id', $this->agent->id);

    $admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->workspace->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);
    actAs($admin, $this->workspace);

    $this->getJson("{$this->api}/agents")->assertOk()->assertJsonCount(2, 'agents')->assertJsonPath('agents.0.agent.id', $admin->id);
});

test('another workspace\'s desk is rejected as a filter', function () {
    actAs($this->agent, $this->workspace);

    $this->getJson("{$this->api}/overview?desk_ids[]={$this->otherDesk->id}")->assertUnprocessable()->assertJsonValidationErrors('desk_ids.0');
    $this->actingAs($this->agent)->get("{$this->web}/overview?desk_ids[]={$this->otherDesk->id}")->assertSessionHasErrors('desk_ids.0');
});

test('a token for one workspace can\'t read another\'s reports', function () {
    actAs($this->agent, $this->workspace);

    $this->getJson('http://other.harbor.test/api/v1/reports/overview')->assertForbidden();
});

test('an unknown report is not found', function () {
    $this->actingAs($this->agent)->get("{$this->web}/nonsense")->assertNotFound();
});

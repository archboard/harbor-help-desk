<?php

use App\Mcp\Servers\HarborServer;
use App\Mcp\Tools\DescribeWorkspaceTool;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;

test('it describes only the desks the agent belongs to, with the workspace catalogs', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $hidden = Desk::factory()->create(['name' => 'Hidden desk']);
    $tag = Tag::factory()->create(['name' => 'vip']);

    $response = HarborServer::actingAs($agent)->tool(DescribeWorkspaceTool::class);

    $response->assertOk()
        ->assertSee([$desk->name, $tag->name, 'on_hold', 'urgent', $agent->email])
        ->assertDontSee($hidden->name);
});

test('another workspace\'s desks, tags and users never appear', function () {
    ['agent' => $agent] = acmeWithAgent();
    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    Desk::factory()->create(['name' => 'Other desk']);
    Tag::factory()->create(['name' => 'other-tag']);
    $stranger = deskMember($other, Desk::where('name', 'Other desk')->first(), attributes: ['email' => 'stranger@example.test']);
    Workspace::where('subdomain', 'acme')->first()->makeCurrent();

    HarborServer::actingAs($agent)->tool(DescribeWorkspaceTool::class)
        ->assertOk()
        ->assertDontSee(['Other desk', 'other-tag', $stranger->email]);
});

test('a user outside the workspace is denied', function () {
    acmeWithAgent();

    HarborServer::actingAs(User::factory()->create())->tool(DescribeWorkspaceTool::class)->assertHasErrors();
});

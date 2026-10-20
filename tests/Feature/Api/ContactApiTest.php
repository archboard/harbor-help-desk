<?php

use App\Models\Contact;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    ['workspace' => $this->workspace, 'agent' => $this->agent] = acmeWithAgent();
    actAs($this->agent, $this->workspace);
});

test('contacts are listed, created, shown and updated', function () {
    $this->postJson('http://acme.harbor.test/api/v1/contacts', ['name' => 'Pat Lee', 'email' => 'pat@acme.test'])
        ->assertCreated()
        ->assertJsonPath('name', 'Pat Lee');
    $pat = Contact::sole();

    $this->getJson('http://acme.harbor.test/api/v1/contacts?search=pat')
        ->assertOk()
        ->assertJsonPath('data', [['id' => $pat->id, 'name' => 'Pat Lee', 'email' => 'pat@acme.test', 'tickets_count' => 0]])
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);

    $this->getJson("http://acme.harbor.test/api/v1/contacts/{$pat->id}")
        ->assertOk()
        ->assertExactJson(['id' => $pat->id, 'name' => 'Pat Lee', 'email' => 'pat@acme.test']);

    $this->patchJson("http://acme.harbor.test/api/v1/contacts/{$pat->id}", ['name' => 'Patricia'])
        ->assertOk()
        ->assertJsonPath('name', 'Patricia');

    $this->postJson('http://acme.harbor.test/api/v1/contacts', ['name' => 'Dup', 'email' => 'PAT@acme.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('a token cannot reach another workspace\'s contacts', function () {
    $foreign = Workspace::factory()->create(['subdomain' => 'other'])->execute(fn () => Contact::factory()->create());
    $this->workspace->makeCurrent();

    $this->getJson("http://acme.harbor.test/api/v1/contacts/{$foreign->id}")->assertNotFound();

    Workspace::forgetCurrent();
    $this->getJson('http://other.harbor.test/api/v1/contacts')->assertForbidden();
});

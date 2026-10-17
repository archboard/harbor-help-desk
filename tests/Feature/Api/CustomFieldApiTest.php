<?php

use App\Enums\WorkspaceRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->desk = $this->acme->execute(fn () => Desk::sole());
});

test('admins define fields and every member can list them', function () {
    actAs($this->admin, $this->acme);

    $this->postJson('http://acme.harbor.test/api/v1/custom-fields', ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'L'], 'position' => 1])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Size')
        ->assertJsonPath('data.type', 'select')
        ->assertJsonPath('data.options', ['S', 'L'])
        ->assertJsonPath('data.is_required', false);
    $this->postJson('http://acme.harbor.test/api/v1/custom-fields', ['name' => 'Order', 'type' => 'text', 'position' => 0])->assertCreated();

    actAs($this->agent, $this->acme);
    $this->getJson('http://acme.harbor.test/api/v1/custom-fields')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Order')
        ->assertJsonPath('data.1.name', 'Size');
});

test('fields are validated and agents cannot manage them', function () {
    actAs($this->admin, $this->acme);
    $this->postJson('http://acme.harbor.test/api/v1/custom-fields', ['type' => 'select'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'options']);

    actAs($this->agent, $this->acme);
    $this->postJson('http://acme.harbor.test/api/v1/custom-fields', ['name' => 'Order', 'type' => 'text'])->assertForbidden();
});

test('admins rename and delete fields', function () {
    $field = $this->acme->execute(fn () => CustomField::factory()->create(['name' => 'Order']));
    actAs($this->admin, $this->acme);

    $this->patchJson("http://acme.harbor.test/api/v1/custom-fields/{$field->id}", ['name' => 'Order number', 'is_required' => true])
        ->assertOk()
        ->assertJsonPath('data.name', 'Order number')
        ->assertJsonPath('data.is_required', true);
    $this->deleteJson("http://acme.harbor.test/api/v1/custom-fields/{$field->id}")->assertNoContent();

    expect(CustomField::withoutGlobalScopes()->count())->toBe(0);
});

test('desk leads enable fields on their desk', function () {
    [$a, $b] = $this->acme->execute(fn () => CustomField::factory()->count(2)->sequence(['position' => 2], ['position' => 1])->create()->all());
    actAs($this->admin, $this->acme);

    $this->putJson("http://acme.harbor.test/api/v1/desks/{$this->desk->id}/custom-fields", ['field_ids' => [$a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $b->id)
        ->assertJsonPath('data.1.id', $a->id);
    $this->getJson("http://acme.harbor.test/api/v1/desks/{$this->desk->id}")
        ->assertJsonPath('data.custom_fields.0.id', $b->id)
        ->assertJsonCount(2, 'data.custom_fields');

    actAs($this->agent, $this->acme);
    $this->putJson("http://acme.harbor.test/api/v1/desks/{$this->desk->id}/custom-fields", ['field_ids' => []])->assertForbidden();
});

test('values are set through the ticket', function () {
    $field = $this->acme->execute(fn () => CustomField::factory()->create(['name' => 'Seats', 'type' => 'number']));
    $ticket = $this->acme->execute(function () use ($field) {
        $this->desk->customFields()->attach($field, ['workspace_id' => $this->acme->id]);

        return Ticket::factory()->for($this->desk)->create();
    });
    actAs($this->admin, $this->acme);

    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}")->assertJsonPath('data.custom_fields', []);

    $this->patchJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}", ['custom_fields' => [$field->id => '12']])
        ->assertOk()
        ->assertJsonPath("data.custom_fields.{$field->id}", 12);
    $this->patchJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}", ['custom_fields' => [$field->id => 'lots']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["custom_fields.{$field->id}"]);
});

test('another workspace fields are invisible and untouched', function () {
    $foreign = $this->other->execute(fn () => CustomField::factory()->create(['name' => 'Renewal']));
    actAs($this->admin, $this->acme);

    $this->getJson('http://acme.harbor.test/api/v1/custom-fields')->assertJsonMissing(['id' => $foreign->id]);
    $this->patchJson("http://acme.harbor.test/api/v1/custom-fields/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->deleteJson("http://acme.harbor.test/api/v1/custom-fields/{$foreign->id}")->assertNotFound();
    $this->putJson("http://acme.harbor.test/api/v1/desks/{$this->desk->id}/custom-fields", ['field_ids' => [$foreign->id]])
        ->assertUnprocessable();

    expect($foreign->fresh()->name)->toBe('Renewal');
});

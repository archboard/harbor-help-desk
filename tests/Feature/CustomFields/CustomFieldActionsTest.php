<?php

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Actions\Desks\SyncDeskCustomFields;
use App\Enums\CustomFieldType;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('admins create, update and delete fields', function () {
    $field = app(CreateCustomField::class)->handle($this->admin, ['name' => 'Order number', 'type' => 'text']);

    expect($field)->workspace_id->toBe($this->workspace->id)->type->toBe(CustomFieldType::TEXT)->is_required->toBeFalse();

    app(UpdateCustomField::class)->handle($this->admin, $field, ['name' => 'Order #', 'is_required' => true, 'position' => 2]);
    expect($field->fresh())->name->toBe('Order #')->is_required->toBeTrue()->position->toBe(2);

    app(DeleteCustomField::class)->handle($this->admin, $field);
    expect(CustomField::count())->toBe(0);
});

test('agents cannot manage fields', function () {
    $field = CustomField::factory()->create();

    expect(fn () => app(CreateCustomField::class)->handle($this->agent, ['name' => 'Order', 'type' => 'text']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateCustomField::class)->handle($this->agent, $field, ['name' => 'Renamed']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteCustomField::class)->handle($this->agent, $field))
        ->toThrow(AuthorizationException::class);
});

test('names are unique per workspace ignoring case', function () {
    CustomField::factory()->create(['name' => 'Order number']);
    $other = CustomField::factory()->create(['name' => 'Product']);
    Workspace::factory()->create()->execute(fn () => CustomField::factory()->create(['name' => 'Renewal']));

    expect(fn () => app(CreateCustomField::class)->handle($this->admin, ['name' => 'ORDER NUMBER', 'type' => 'text']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $other, ['name' => 'order number']))
        ->toThrow(ValidationException::class);

    app(CreateCustomField::class)->handle($this->admin, ['name' => 'Renewal', 'type' => 'date']);
    app(UpdateCustomField::class)->handle($this->admin, $other, ['name' => 'PRODUCT']);
});

test('options are required for select types and prohibited for the rest', function (array $input, bool $valid) {
    $attempt = fn () => app(CreateCustomField::class)->handle($this->admin, ['name' => 'Field', ...$input]);

    $valid ? expect($attempt())->toBeInstanceOf(CustomField::class) : expect($attempt)->toThrow(ValidationException::class);
})->with([
    'select with options' => [['type' => 'select', 'options' => ['A', 'B']], true],
    'multi-select with options' => [['type' => 'multi_select', 'options' => ['A']], true],
    'select without options' => [['type' => 'select'], false],
    'select with empty options' => [['type' => 'select', 'options' => []], false],
    'select with duplicate options' => [['type' => 'select', 'options' => ['A', 'A']], false],
    'select with non-string option' => [['type' => 'select', 'options' => [['A']]], false],
    'text with options' => [['type' => 'text', 'options' => ['A']], false],
    'text without options' => [['type' => 'text'], true],
    'unknown type' => [['type' => 'color'], false],
]);

test('a field type cannot be changed', function () {
    $field = CustomField::factory()->create();

    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $field, ['type' => 'number']))
        ->toThrow(ValidationException::class);

    app(UpdateCustomField::class)->handle($this->admin, $field, ['type' => 'text', 'name' => 'Same type']);
    expect($field->fresh()->name)->toBe('Same type');
});

test('select fields keep requiring options when updated', function () {
    $field = CustomField::factory()->select()->create();

    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $field, ['options' => []]))
        ->toThrow(ValidationException::class);

    app(UpdateCustomField::class)->handle($this->admin, $field, ['options' => ['Only']]);
    expect($field->fresh()->options)->toBe(['Only']);

    $text = CustomField::factory()->create();
    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $text, ['options' => ['A']]))
        ->toThrow(ValidationException::class);

    app(UpdateCustomField::class)->handle($this->admin, $text, ['options' => null]);
    expect($text->fresh()->options)->toBeNull();

    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $field, ['options' => null]))
        ->toThrow(ValidationException::class);
});

test('deleting a field strips its values from tickets and leaves the others', function () {
    $field = CustomField::factory()->create();
    $kept = CustomField::factory()->create();
    $ticket = Ticket::factory()->create(['custom_fields' => [$field->id => 'ACME-42', $kept->id => 'x']]);
    $untouched = Ticket::factory()->create(['custom_fields' => [$kept->id => 'y']]);

    app(DeleteCustomField::class)->handle($this->admin, $field);

    expect($ticket->fresh()->custom_fields)->toBe([$kept->id => 'x']);
    expect($untouched->fresh()->custom_fields)->toBe([$kept->id => 'y']);
});

test('desk leads can sync their desk fields and agents cannot', function () {
    $desk = Desk::factory()->create();
    [$a, $b] = CustomField::factory()->count(2)->create();
    $lead = deskMember($this->workspace, $desk, DeskRole::LEAD);
    $agent = deskMember($this->workspace, $desk);

    app(SyncDeskCustomFields::class)->handle($lead, $desk, ['field_ids' => [$a->id, $b->id]]);
    expect($desk->customFields()->pluck('custom_fields.id')->all())->toEqualCanonicalizing([$a->id, $b->id]);

    app(SyncDeskCustomFields::class)->handle($lead, $desk, ['field_ids' => [$b->id]]);
    expect($desk->customFields()->pluck('custom_fields.id')->all())->toBe([$b->id]);

    app(SyncDeskCustomFields::class)->handle($lead, $desk, ['field_ids' => []]);
    expect($desk->customFields()->count())->toBe(0);

    expect(fn () => app(SyncDeskCustomFields::class)->handle($agent, $desk, ['field_ids' => [$a->id]]))
        ->toThrow(AuthorizationException::class);
});

test('desk fields are listed by position', function () {
    $desk = Desk::factory()->create();
    $second = CustomField::factory()->create(['position' => 2]);
    $first = CustomField::factory()->create(['position' => 1]);

    app(SyncDeskCustomFields::class)->handle($this->admin, $desk, ['field_ids' => [$second->id, $first->id]]);

    expect($desk->customFields->modelKeys())->toBe([$first->id, $second->id]);
});

test('fields and desks from another workspace are out of reach', function () {
    $other = Workspace::factory()->create();
    [$foreignField, $foreignDesk] = $other->execute(fn () => [CustomField::factory()->create(['name' => 'Order number']), Desk::factory()->create()]);
    $desk = Desk::factory()->create();

    expect(fn () => app(SyncDeskCustomFields::class)->handle($this->admin, $desk, ['field_ids' => [$foreignField->id]]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SyncDeskCustomFields::class)->handle($this->admin, $foreignDesk, ['field_ids' => []]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateCustomField::class)->handle($this->admin, $foreignField, ['name' => 'Hijacked']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteCustomField::class)->handle($this->admin, $foreignField))
        ->toThrow(AuthorizationException::class);

    expect($foreignField->fresh())->name->toBe('Order number');
    expect($desk->customFields()->count())->toBe(0);
});

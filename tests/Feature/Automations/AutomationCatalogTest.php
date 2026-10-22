<?php

use App\Automations\FieldGroup;
use App\Automations\FieldType;
use App\Automations\Operator;
use App\Automations\PlaceholderRenderer;
use App\Enums\CustomFieldType;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->desk = $this->acme->execute(fn () => Desk::sole());
    $this->secondDesk = $this->acme->execute(fn () => Desk::factory()->create(['name' => 'Billing']));

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->lead = deskMember($this->acme, $this->desk, DeskRole::LEAD, ['name' => 'Lead Lee']);
    $this->deskMate = deskMember($this->acme, $this->desk, DeskRole::AGENT, ['name' => 'Desk Mate']);
    $this->stranger = deskMember($this->acme, $this->secondDesk, DeskRole::AGENT, ['name' => 'Stranger Sam']);

    $this->acme->execute(function () {
        $this->field = CustomField::factory()->create(['name' => 'Plan', 'type' => CustomFieldType::SELECT, 'options' => ['free', 'pro']]);
        Tag::factory()->create(['name' => 'vip']);
    });
    $this->other->execute(fn () => CustomField::factory()->create(['name' => 'Secret']));

    $this->webUrl = 'http://acme.harbor.test/automations/catalog';
    $this->apiUrl = 'http://acme.harbor.test/api/v1/automations/catalog';
});

function catalogField(array $json, string $key): array
{
    return collect($json['field_groups'])->pluck('fields')->flatten(1)->firstWhere('key', $key);
}

test('the catalog has the expected shape on the web and the API', function () {
    $this->actingAs($this->admin)->getJson($this->webUrl)
        ->assertOk()
        ->assertJsonStructure(['triggers', 'field_groups' => [['group', 'label', 'fields']], 'operators', 'actions', 'placeholders'])
        ->assertJsonPath('triggers.*.key', ['ticket.created', 'ticket.comment_added', 'ticket.field_changed', 'ticket.sla', 'schedule', 'webhook'])
        ->assertJsonPath('triggers.5.placeholders.*.key', ['webhook.payload.*'])
        ->assertJsonPath('triggers.2.placeholders.*.key', ['event.field', 'event.from', 'event.to'])
        ->assertJsonPath('actions.*.key', ['set_field', 'tags', 'assign', 'move_desk', 'set_sla_policy', 'set_reminder', 'add_note', 'send_reply', 'apply_macro', 'notify', 'send_webhook'])
        ->assertJsonPath('actions.0.config_type', 'App.Data.Automations.Actions.SetFieldConfigData')
        ->assertJsonCount(count(FieldGroup::cases()), 'field_groups')
        ->assertJsonPath('placeholders.0', ['key' => 'requester.name', 'label' => 'Requester name']);

    actAs($this->admin, $this->acme);
    $json = $this->getJson($this->apiUrl)->assertOk()->json();

    expect(catalogField($json, 'ticket.status'))->toMatchArray(['label' => 'Status', 'type' => 'enum'])
        ->and(collect(catalogField($json, 'ticket.status')['options'])->pluck('value'))->toContain('open')
        ->and(catalogField($json, 'ticket.tags')['options'])->toBe([['value' => 'vip', 'label' => 'vip']])
        ->and(catalogField($json, 'ticket.desk')['options'])->toHaveCount(2);
});

test('the catalog lists the operators of every field type', function () {
    $this->actingAs($this->admin);
    $operators = $this->getJson($this->webUrl)->json('operators');

    expect(array_keys($operators))->toBe(array_map(fn (FieldType $type): string => $type->value, FieldType::cases()));

    foreach (FieldType::cases() as $type) {
        $expected = collect(Operator::cases())->filter(fn (Operator $operator): bool => $operator->supports($type))->map->value->values()->all();

        expect(collect($operators[$type->value])->pluck('value')->all())->toBe($expected);
    }

    expect(collect($operators['string'])->firstWhere('value', 'is_empty'))->toBe(['value' => 'is_empty', 'label' => 'is empty', 'takes_value' => false]);
});

test('an admin sees every desk and member, a lead only their own desks', function () {
    $this->actingAs($this->admin);
    $admin = $this->getJson($this->webUrl)->json();

    expect(collect(catalogField($admin, 'ticket.desk')['options'])->pluck('value')->all())->toEqualCanonicalizing([$this->desk->id, $this->secondDesk->id])
        ->and(collect(catalogField($admin, 'ticket.assignees')['options'])->pluck('label')->all())->toContain('Stranger Sam', 'Lead Lee');

    $this->actingAs($this->lead);
    $lead = $this->getJson($this->webUrl)->assertOk()->json();

    expect(catalogField($lead, 'ticket.desk')['options'])->toBe([['value' => $this->desk->id, 'label' => $this->desk->name]])
        ->and(collect(catalogField($lead, 'ticket.assignees')['options'])->pluck('label')->all())->toEqualCanonicalizing(['Lead Lee', 'Desk Mate'])
        ->and(collect(catalogField($lead, 'event.actor')['options'])->pluck('label')->all())->not->toContain('Stranger Sam');
});

test('agents cannot read the catalog', function () {
    $this->actingAs($this->deskMate)->getJson($this->webUrl)->assertForbidden();

    actAs($this->deskMate, $this->acme);
    $this->getJson($this->apiUrl)->assertForbidden();
});

test('an API token without the workspace scope is rejected', function () {
    Passport::actingAs($this->admin, []);

    $this->getJson($this->apiUrl)->assertForbidden();
});

test('custom fields come from the current workspace only', function () {
    $this->actingAs($this->admin);
    $json = $this->getJson($this->webUrl)->json();
    $custom = collect($json['field_groups'])->firstWhere('group', 'custom_fields')['fields'];

    expect($custom)->toHaveCount(1)
        ->and($custom[0])->toMatchArray(['key' => "custom_field.{$this->field->id}", 'label' => 'Plan', 'type' => 'enum'])
        ->and($custom[0]['options'])->toBe([['value' => 'free', 'label' => 'free'], ['value' => 'pro', 'label' => 'pro']]);
});

test('the placeholder renderer fills known placeholders, takes extras and leaves unknown ones', function () {
    $this->acme->makeCurrent();
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create(['name' => 'Ann Agent']);

    $text = '{{ agent.first_name }} re #{{ ticket.number }} {{ message.body }} {{ nope }}';

    expect(app(PlaceholderRenderer::class)->render($text, $ticket, $agent, ['message.body' => 'Hi']))
        ->toBe("Ann re #{$ticket->number} Hi {{ nope }}")
        ->and(array_keys(PlaceholderRenderer::labels()))->toContain('desk.name');
});

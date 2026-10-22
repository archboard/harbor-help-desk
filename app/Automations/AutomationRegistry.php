<?php

namespace App\Automations;

use App\Automations\Actions\AutomationAction;
use App\Automations\Fields\CoreFields;
use App\Automations\Fields\CustomFieldFieldFactory;
use App\Automations\Fields\Field;
use App\Automations\Triggers\Trigger;
use App\Models\CustomField;

/**
 * Everything an automation can be built from: triggers, fields and actions. Built from lists of
 * classes in the service provider. Fields are looked up by key, and custom fields only
 * resolve inside the current workspace.
 */
class AutomationRegistry
{
    /** @var array<string, Field>|null */
    protected ?array $coreFields = null;

    /**
     * @param  list<class-string<Trigger>>  $triggers
     * @param  list<class-string<AutomationAction>>  $actions
     */
    public function __construct(protected array $triggers = [], protected array $actions = []) {}

    /**
     * @return array<string, Trigger>
     */
    public function triggers(): array
    {
        return collect($this->triggers)->map(fn (string $class): Trigger => app($class))->keyBy(fn (Trigger $trigger): string => $trigger->key())->all();
    }

    public function trigger(string $key): ?Trigger
    {
        return $this->triggers()[$key] ?? null;
    }

    /**
     * @return array<string, AutomationAction>
     */
    public function actions(): array
    {
        return collect($this->actions)->map(fn (string $class): AutomationAction => app($class))->keyBy(fn (AutomationAction $action): string => $action->key())->all();
    }

    public function action(string $key): ?AutomationAction
    {
        return $this->actions()[$key] ?? null;
    }

    /**
     * Every listable field: the built-in ones and the current workspace's custom fields.
     * Webhook payload fields have free paths, so they're only found through {@see self::field()}.
     *
     * @return array<string, Field>
     */
    public function fields(): array
    {
        $custom = CustomField::query()->orderBy('position')->orderBy('name')->get()
            ->map(CustomFieldFieldFactory::make(...))
            ->keyBy(fn (Field $field): string => $field->key());

        return $this->core() + $custom->all();
    }

    /**
     * Find a field by key, or null when it doesn't exist in the current workspace.
     * Webhook payload keys are `webhook.payload.<path>` (string), `webhook.payload_number.<path>`
     * and `webhook.payload_boolean.<path>`.
     */
    public function field(string $key): ?Field
    {
        if (isset($this->core()[$key])) {
            return $this->core()[$key];
        }

        if (str_starts_with($key, 'custom_field.')) {
            // ponytail: one query per custom-field lookup; memoize per workspace if a run needs many
            $customField = CustomField::query()->find(substr($key, strlen('custom_field.')));

            return $customField ? CustomFieldFieldFactory::make($customField) : null;
        }

        if (preg_match('/^webhook\.(payload|payload_number|payload_boolean)\.(.+)$/', $key, $match) === 1) {
            return CoreFields::webhookPayload($match[2], match ($match[1]) {
                'payload_number' => FieldType::NUMBER,
                'payload_boolean' => FieldType::BOOLEAN,
                default => FieldType::STRING,
            });
        }

        return null;
    }

    /**
     * @return array<string, Field>
     */
    protected function core(): array
    {
        return $this->coreFields ??= collect(CoreFields::all())->keyBy(fn (Field $field): string => $field->key())->all();
    }
}

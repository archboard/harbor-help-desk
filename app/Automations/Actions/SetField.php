<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\UpdateTicket;
use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\Actions\SetFieldConfigData;
use App\Data\Automations\DelayForData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Sets the status, priority, subject, due date or a custom field, through {@see UpdateTicket}.
 * A due date is absolute or relative to now, in business time when the interval names a schedule.
 */
class SetField extends ConfiguredAction
{
    protected const array BUILT_IN = ['ticket.status', 'ticket.priority', 'ticket.subject', 'ticket.due_at'];

    public function key(): string
    {
        return 'set_field';
    }

    public function label(): string
    {
        return __('Set a field');
    }

    public function configData(): string
    {
        return SetFieldConfigData::class;
    }

    /**
     * Whether the field key names something this action can set: the built-in fields and the custom fields.
     * Whether a custom field still exists is checked when saving.
     */
    public static function canSet(string $key): bool
    {
        return in_array($key, self::BUILT_IN, true) || str_starts_with($key, 'custom_field.');
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [
            'field' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || (! self::canSet($value) || (str_starts_with($value, 'custom_field.') && $this->customField($value) === null))) {
                    $fail(__('This field does not exist.'));
                }
            }],
            'value' => [new class implements DataAwareRule, ValidationRule
            {
                public bool $implicit = true;

                /** @var array<string, mixed> */
                protected array $data = [];

                /**
                 * @param  array<string, mixed>  $data
                 */
                public function setData(array $data): static
                {
                    $this->data = $data;

                    return $this;
                }

                public function validate(string $attribute, mixed $value, Closure $fail): void
                {
                    $rules = SetField::valueRules($this->data['field'] ?? null, $value);

                    foreach (Validator::make(['value' => $value], $rules)->errors()->all() as $message) {
                        $fail($message);
                    }
                }
            }],
        ];
    }

    /**
     * The rules for a value of the field, empty for a field that doesn't exist.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function valueRules(mixed $field, mixed $value): array
    {
        if (! is_string($field)) {
            return [];
        }

        if (str_starts_with($field, 'custom_field.')) {
            $custom = CustomField::query()->find(substr($field, strlen('custom_field.')));

            return $custom === null ? [] : [
                'value' => [$custom->is_required ? 'required' : 'nullable', ...$custom->type->rules($custom)],
                'value.*' => $custom->type->itemRules($custom),
            ];
        }

        return match ($field) {
            'ticket.status' => ['value' => ['required', Rule::enum(TicketStatus::class)]],
            'ticket.priority' => ['value' => ['required', Rule::enum(TicketPriority::class)]],
            'ticket.subject' => ['value' => ['required', 'string', 'max:255']],
            'ticket.due_at' => is_array($value)
                ? ['value' => ['array:relative'], ...DelayForData::rulesAt('value.relative')]
                : ['value' => ['nullable', 'date']],
            default => [],
        };
    }

    public function execute(Context $context, array $config): string
    {
        $config = SetFieldConfigData::from($config);

        if (str_starts_with($config->field, 'custom_field.')) {
            $custom = $this->customField($config->field) ?? throw new AutomationException(__('The custom field no longer exists.'));
            $input = ['custom_fields' => [$custom->getKey() => $config->value]];
        } else {
            $input = match ($config->field) {
                'ticket.status' => ['status' => $config->value],
                'ticket.priority' => ['priority' => $config->value],
                'ticket.subject' => ['subject' => $config->value],
                'ticket.due_at' => ['due_at' => $this->dueAt($config->value)?->toIso8601String()],
                default => throw new AutomationException(__('The field “:field” does not exist.', ['field' => $config->field])),
            };
        }

        app(UpdateTicket::class)->handle($this->actor($context), $context->ticket, $input);

        return __('Set :field to :value.', ['field' => $this->fieldLabel($config->field), 'value' => $this->valueLabel($config)]);
    }

    public function describe(Context $context, array $config): string
    {
        $config = SetFieldConfigData::from($config);

        return __('Would set :field to :value.', ['field' => $this->fieldLabel($config->field), 'value' => $this->valueLabel($config)]);
    }

    protected function customField(string $field): ?CustomField
    {
        return str_starts_with($field, 'custom_field.') ? CustomField::query()->find(substr($field, strlen('custom_field.'))) : null;
    }

    protected function dueAt(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            is_array($value) => $this->after(DelayForData::from($value['relative'] ?? [])),
            $value === null => null,
            default => CarbonImmutable::parse($value),
        };
    }

    protected function fieldLabel(string $field): string
    {
        return match (true) {
            $field === 'ticket.status' => __('status'),
            $field === 'ticket.priority' => __('priority'),
            $field === 'ticket.subject' => __('subject'),
            $field === 'ticket.due_at' => __('due date'),
            default => $this->customField($field)->name ?? $field,
        };
    }

    protected function valueLabel(SetFieldConfigData $config): string
    {
        return match (true) {
            is_array($config->value) && isset($config->value['relative']) => __('“:amount :unit from now”', ['amount' => $config->value['relative']['amount'] ?? '', 'unit' => $config->value['relative']['unit'] ?? '']),
            is_array($config->value) => implode(', ', array_map(strval(...), $config->value)),
            $config->value === null => __('nothing'),
            is_bool($config->value) => $config->value ? __('yes') : __('no'),
            default => (string) $config->value,
        };
    }
}

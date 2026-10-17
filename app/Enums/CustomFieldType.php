<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Models\CustomField;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum CustomFieldType: string
{
    use HasLabels;

    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case DECIMAL = 'decimal';
    case DATE = 'date';
    case CHECKBOX = 'checkbox';
    case SELECT = 'select';
    case MULTI_SELECT = 'multi_select';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::TEXTAREA => __('Long text'),
            self::NUMBER => __('Number'),
            self::DECIMAL => __('Decimal'),
            self::DATE => __('Date'),
            self::CHECKBOX => __('Checkbox'),
            self::SELECT => __('Select'),
            self::MULTI_SELECT => __('Multi-select'),
        };
    }

    /**
     * Whether the field's definition carries a list of choices.
     */
    public function hasOptions(): bool
    {
        return $this === self::SELECT || $this === self::MULTI_SELECT;
    }

    /**
     * The validation rules for one value of this type on the given field. Multi-select
     * choices are checked per item with {@see self::itemRules()}.
     *
     * @return array<int, In|string>
     */
    public function rules(CustomField $field): array
    {
        return match ($this) {
            self::TEXT => ['string', 'max:255'],
            self::TEXTAREA => ['string', 'max:65535'],
            self::NUMBER => ['integer'],
            self::DECIMAL => ['numeric'],
            self::DATE => ['date_format:Y-m-d'],
            self::CHECKBOX => ['boolean'],
            self::SELECT => ['string', Rule::in($field->options ?? [])],
            self::MULTI_SELECT => ['array'],
        };
    }

    /**
     * The rules for each item of an array value, i.e. the field's choices.
     *
     * @return array<int, In|string>
     */
    public function itemRules(CustomField $field): array
    {
        return $this === self::MULTI_SELECT ? [Rule::in($field->options ?? []), 'distinct'] : [];
    }

    /**
     * Turn a validated value into the scalar stored as JSON, e.g. "42" into 42.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::NUMBER => (int) $value,
            self::DECIMAL => (float) $value,
            self::CHECKBOX => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::MULTI_SELECT => array_values($value),
            default => $value,
        };
    }
}

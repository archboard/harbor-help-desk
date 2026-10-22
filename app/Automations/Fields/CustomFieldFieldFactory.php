<?php

namespace App\Automations\Fields;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Automations\FieldType;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Carbon\CarbonImmutable;

/**
 * Turns a workspace custom field into a {@see Field} keyed `custom_field.{id}`.
 */
class CustomFieldFieldFactory
{
    public static function make(CustomField $customField): Field
    {
        $id = $customField->getKey();
        $type = match ($customField->type) {
            CustomFieldType::TEXT => FieldType::STRING,
            CustomFieldType::TEXTAREA => FieldType::TEXT,
            CustomFieldType::NUMBER, CustomFieldType::DECIMAL => FieldType::NUMBER,
            CustomFieldType::DATE => FieldType::DATETIME,
            CustomFieldType::CHECKBOX => FieldType::BOOLEAN,
            CustomFieldType::SELECT => FieldType::ENUM,
            CustomFieldType::MULTI_SELECT => FieldType::MULTI_ENUM,
        };

        return new ClosureField(
            key: "custom_field.$id",
            label: $customField->name,
            group: FieldGroup::CUSTOM_FIELDS,
            type: $type,
            resolver: fn (Context $context): mixed => match ($customField->type) {
                CustomFieldType::DATE => ($value = $context->ticket->custom_fields[$id] ?? null) === null ? null : CarbonImmutable::parse($value),
                CustomFieldType::CHECKBOX => (bool) ($context->ticket->custom_fields[$id] ?? false),
                default => $context->ticket->custom_fields[$id] ?? null,
            },
            options: fn (): array => array_combine($customField->options ?? [], $customField->options ?? []),
        );
    }
}

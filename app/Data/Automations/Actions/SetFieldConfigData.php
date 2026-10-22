<?php

namespace App\Data\Automations\Actions;

class SetFieldConfigData extends ActionConfigData
{
    /**
     * @param  string  $field  `ticket.status`, `ticket.priority`, `ticket.subject`, `ticket.due_at` or `custom_field.{id}`.
     * @param  mixed  $value  The new value. For `ticket.due_at`: null, a date, or `{relative: {amount, unit, business_schedule_id?}}` from now.
     */
    public function __construct(
        public string $field,
        public mixed $value,
    ) {}

    public static function rules(): array
    {
        return [
            'field' => ['required', 'string'],
            'value' => ['present'],
        ];
    }
}

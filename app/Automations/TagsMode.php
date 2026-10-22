<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

enum TagsMode: string
{
    use HasLabels;

    case ADD = 'add';
    case REMOVE = 'remove';
    case REPLACE = 'replace';

    public function label(): string
    {
        return match ($this) {
            self::ADD => __('Add'),
            self::REMOVE => __('Remove'),
            self::REPLACE => __('Replace'),
        };
    }
}

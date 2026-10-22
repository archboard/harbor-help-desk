<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

enum AssignMode: string
{
    use HasLabels;

    case ADD = 'add';
    case REPLACE = 'replace';
    case UNASSIGN_ALL = 'unassign_all';
    case ROUND_ROBIN = 'round_robin';
    case LOAD_BALANCED = 'load_balanced';

    public function label(): string
    {
        return match ($this) {
            self::ADD => __('Add assignees'),
            self::REPLACE => __('Replace assignees'),
            self::UNASSIGN_ALL => __('Unassign everyone'),
            self::ROUND_ROBIN => __('Round robin'),
            self::LOAD_BALANCED => __('Load balanced'),
        };
    }
}

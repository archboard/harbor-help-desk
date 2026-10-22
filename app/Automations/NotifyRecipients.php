<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

enum NotifyRecipients: string
{
    use HasLabels;

    case ASSIGNEES = 'assignees';
    case DESK_MEMBERS = 'desk_members';
    case DESK_LEADS = 'desk_leads';
    case USERS = 'users';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNEES => __('The assignees'),
            self::DESK_MEMBERS => __('The desk’s members'),
            self::DESK_LEADS => __('The desk’s leads'),
            self::USERS => __('Chosen people'),
        };
    }
}

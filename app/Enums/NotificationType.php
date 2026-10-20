<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum NotificationType: string
{
    use HasLabels;

    case NEW_TICKET = 'new_ticket';
    case ASSIGNED = 'assigned';
    case REQUESTER_REPLIED = 'requester_replied';
    case TICKET_UPDATED = 'ticket_updated';
    case MENTIONED = 'mentioned';
    case REMINDER_DUE = 'reminder_due';

    public function label(): string
    {
        return match ($this) {
            self::NEW_TICKET => __('New ticket'),
            self::ASSIGNED => __('Assigned to you'),
            self::REQUESTER_REPLIED => __('Requester replied'),
            self::TICKET_UPDATED => __('Ticket updated'),
            self::MENTIONED => __('Mentioned'),
            self::REMINDER_DUE => __('Reminder due'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NEW_TICKET => __('A new ticket is created on one of your desks.'),
            self::ASSIGNED => __('A ticket is assigned to you.'),
            self::REQUESTER_REPLIED => __('The requester replies on a ticket assigned to you.'),
            self::TICKET_UPDATED => __('Someone else changes a ticket assigned to you.'),
            self::MENTIONED => __('Someone mentions you in a comment.'),
            self::REMINDER_DUE => __('A reminder you set is due.'),
        };
    }

    /**
     * The channels used until the member chooses otherwise.
     *
     * @return array<int, NotificationChannel>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::NEW_TICKET, self::TICKET_UPDATED => [NotificationChannel::DATABASE],
            self::ASSIGNED, self::REQUESTER_REPLIED, self::MENTIONED, self::REMINDER_DUE => [NotificationChannel::DATABASE, NotificationChannel::MAIL],
        };
    }
}

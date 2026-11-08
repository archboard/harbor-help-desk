<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TicketEventType: string
{
    use HasLabels;

    case CREATED = 'created';
    case SUBJECT_CHANGED = 'subject_changed';
    case STATUS_CHANGED = 'status_changed';
    case PRIORITY_CHANGED = 'priority_changed';
    case DUE_DATE_CHANGED = 'due_date_changed';
    case DESK_CHANGED = 'desk_changed';
    case SLA_POLICY_CHANGED = 'sla_policy_changed';
    case CUSTOM_FIELD_CHANGED = 'custom_field_changed';
    case ASSIGNEE_ADDED = 'assignee_added';
    case ASSIGNEE_REMOVED = 'assignee_removed';
    case TAG_ADDED = 'tag_added';
    case TAG_REMOVED = 'tag_removed';
    case COMMENT_ADDED = 'comment_added';
    case INTERNAL_NOTE_ADDED = 'internal_note_added';
    case COMMENT_EDITED = 'comment_edited';
    case COMMENT_DELETED = 'comment_deleted';
    case ATTACHMENT_ADDED = 'attachment_added';
    case ATTACHMENT_REMOVED = 'attachment_removed';
    case REMINDER_SET = 'reminder_set';
    case SLA_WARNING = 'sla_warning';
    case SLA_BREACHED = 'sla_breached';
    case EMAIL_BOUNCED = 'email_bounced';
    case SATISFACTION_RATED = 'satisfaction_rated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => __('Ticket created'),
            self::SUBJECT_CHANGED => __('Subject changed'),
            self::STATUS_CHANGED => __('Status changed'),
            self::PRIORITY_CHANGED => __('Priority changed'),
            self::DUE_DATE_CHANGED => __('Due date changed'),
            self::DESK_CHANGED => __('Desk changed'),
            self::SLA_POLICY_CHANGED => __('SLA policy changed'),
            self::CUSTOM_FIELD_CHANGED => __('Custom field changed'),
            self::ASSIGNEE_ADDED => __('Assignee added'),
            self::ASSIGNEE_REMOVED => __('Assignee removed'),
            self::TAG_ADDED => __('Tag added'),
            self::TAG_REMOVED => __('Tag removed'),
            self::COMMENT_ADDED => __('Comment added'),
            self::INTERNAL_NOTE_ADDED => __('Internal note added'),
            self::COMMENT_EDITED => __('Comment edited'),
            self::COMMENT_DELETED => __('Comment deleted'),
            self::ATTACHMENT_ADDED => __('Attachment added'),
            self::ATTACHMENT_REMOVED => __('Attachment removed'),
            self::REMINDER_SET => __('Reminder set'),
            self::SLA_WARNING => __('SLA warning'),
            self::SLA_BREACHED => __('SLA breached'),
            self::EMAIL_BOUNCED => __('Email bounced'),
            self::SATISFACTION_RATED => __('Satisfaction rated'),
            self::DELETED => __('Ticket deleted'),
            self::RESTORED => __('Ticket restored'),
        };
    }
}

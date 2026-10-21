<?php

namespace App\Enums;

enum TicketEventType: string
{
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
    case EMAIL_BOUNCED = 'email_bounced';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * What an admin or configuration change did, for the audit log. Later features (exports,
 * erasure, import) add their own cases.
 */
enum AuditAction: string
{
    use HasLabels;

    case WORKSPACE_UPDATED = 'workspace_updated';
    case WORKSPACE_SETTINGS_UPDATED = 'workspace_settings_updated';
    case DESK_CREATED = 'desk_created';
    case DESK_UPDATED = 'desk_updated';
    case DESK_ARCHIVED = 'desk_archived';
    case DESK_RESTORED = 'desk_restored';
    case DESK_MEMBER_SET = 'desk_member_set';
    case DESK_MEMBER_REMOVED = 'desk_member_removed';
    case DESK_CUSTOM_FIELDS_SYNCED = 'desk_custom_fields_synced';
    case CUSTOM_FIELD_CREATED = 'custom_field_created';
    case CUSTOM_FIELD_UPDATED = 'custom_field_updated';
    case CUSTOM_FIELD_DELETED = 'custom_field_deleted';
    case MAILBOX_CREATED = 'mailbox_created';
    case MAILBOX_UPDATED = 'mailbox_updated';
    case MAILBOX_DELETED = 'mailbox_deleted';
    case MACRO_CREATED = 'macro_created';
    case MACRO_UPDATED = 'macro_updated';
    case MACRO_DELETED = 'macro_deleted';
    case AUTOMATION_CREATED = 'automation_created';
    case AUTOMATION_UPDATED = 'automation_updated';
    case AUTOMATION_DELETED = 'automation_deleted';
    case AUTOMATION_ENABLED = 'automation_enabled';
    case AUTOMATION_DISABLED = 'automation_disabled';
    case AUTOMATION_WEBHOOK_TOKEN_ROTATED = 'automation_webhook_token_rotated';
    case SLA_POLICY_CREATED = 'sla_policy_created';
    case SLA_POLICY_UPDATED = 'sla_policy_updated';
    case SLA_POLICY_DELETED = 'sla_policy_deleted';
    case BUSINESS_SCHEDULE_CREATED = 'business_schedule_created';
    case BUSINESS_SCHEDULE_UPDATED = 'business_schedule_updated';
    case BUSINESS_SCHEDULE_DELETED = 'business_schedule_deleted';
    case API_TOKEN_CREATED = 'api_token_created';
    case API_TOKEN_REVOKED = 'api_token_revoked';

    public function label(): string
    {
        return match ($this) {
            self::WORKSPACE_UPDATED => __('Workspace updated'),
            self::WORKSPACE_SETTINGS_UPDATED => __('Workspace settings updated'),
            self::DESK_CREATED => __('Desk created'),
            self::DESK_UPDATED => __('Desk updated'),
            self::DESK_ARCHIVED => __('Desk archived'),
            self::DESK_RESTORED => __('Desk restored'),
            self::DESK_MEMBER_SET => __('Desk member added or changed'),
            self::DESK_MEMBER_REMOVED => __('Desk member removed'),
            self::DESK_CUSTOM_FIELDS_SYNCED => __('Desk custom fields changed'),
            self::CUSTOM_FIELD_CREATED => __('Custom field created'),
            self::CUSTOM_FIELD_UPDATED => __('Custom field updated'),
            self::CUSTOM_FIELD_DELETED => __('Custom field deleted'),
            self::MAILBOX_CREATED => __('Mailbox created'),
            self::MAILBOX_UPDATED => __('Mailbox updated'),
            self::MAILBOX_DELETED => __('Mailbox deleted'),
            self::MACRO_CREATED => __('Macro created'),
            self::MACRO_UPDATED => __('Macro updated'),
            self::MACRO_DELETED => __('Macro deleted'),
            self::AUTOMATION_CREATED => __('Automation created'),
            self::AUTOMATION_UPDATED => __('Automation updated'),
            self::AUTOMATION_DELETED => __('Automation deleted'),
            self::AUTOMATION_ENABLED => __('Automation enabled'),
            self::AUTOMATION_DISABLED => __('Automation disabled'),
            self::AUTOMATION_WEBHOOK_TOKEN_ROTATED => __('Automation webhook URL rotated'),
            self::SLA_POLICY_CREATED => __('SLA policy created'),
            self::SLA_POLICY_UPDATED => __('SLA policy updated'),
            self::SLA_POLICY_DELETED => __('SLA policy deleted'),
            self::BUSINESS_SCHEDULE_CREATED => __('Business schedule created'),
            self::BUSINESS_SCHEDULE_UPDATED => __('Business schedule updated'),
            self::BUSINESS_SCHEDULE_DELETED => __('Business schedule deleted'),
            self::API_TOKEN_CREATED => __('API token created'),
            self::API_TOKEN_REVOKED => __('API token revoked'),
        };
    }
}

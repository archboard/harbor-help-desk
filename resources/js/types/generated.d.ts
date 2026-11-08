declare namespace App {
  namespace Automations {
    export type AssignMode =
      | 'add'
      | 'replace'
      | 'unassign_all'
      | 'round_robin'
      | 'load_balanced';
    export type DelayUnit = 'minutes' | 'hours' | 'days';
    export type FieldGroup =
      | 'ticket'
      | 'requester'
      | 'message'
      | 'event'
      | 'time'
      | 'sla'
      | 'custom_fields'
      | 'webhook';
    export type FieldType =
      | 'string'
      | 'text'
      | 'enum'
      | 'multi_enum'
      | 'user'
      | 'users'
      | 'desk'
      | 'number'
      | 'boolean'
      | 'datetime'
      | 'duration';
    export type MatchMode = 'all' | 'any';
    export type NotifyRecipients =
      | 'assignees'
      | 'desk_members'
      | 'desk_leads'
      | 'users';
    export type Operator =
      | 'is'
      | 'is_not'
      | 'in'
      | 'not_in'
      | 'contains'
      | 'not_contains'
      | 'starts_with'
      | 'ends_with'
      | 'matches'
      | 'is_empty'
      | 'is_not_empty'
      | 'gt'
      | 'gte'
      | 'lt'
      | 'lte'
      | 'includes_any'
      | 'includes_all'
      | 'excludes'
      | 'changed'
      | 'changed_to'
      | 'changed_from';
    export type TagsMode = 'add' | 'remove' | 'replace';
  }
  namespace Data {
    export type InputData = object;
    namespace ApiTokens {
      export type ApiTokenData = {
        id: string;
        name: string | null;
        workspaces: string[];
        created_at: string | null;
        expires_at: string | null;
      };
      export type CreatePersonalAccessTokenData = {
        name: string;
        workspaces: string[];
      };
    }
    namespace Audit {
      export type AuditEventData = {
        id: string;
        action: App.Enums.AuditAction;
        description: string;
        actor_name: string;
        actor_type: string | null;
        actor_id: string | null;
        subject_type: string | null;
        subject_id: string | null;
        properties: Record<string, any>;
        ip: string | null;
        created_at: string;
      };
      export type ListAuditEventsData = {
        action?: App.Enums.AuditAction;
        actor_id?: string;
        from?: string;
        to?: string;
      };
      export type ListWorkspaceTicketEventsData = {
        type?: App.Enums.TicketEventType;
        actor_id?: string;
        from?: string;
        to?: string;
      };
    }
    namespace Auth {
      export type ResetUserPasswordData = {
        password: string;
      };
    }
    namespace Automations {
      export type ActionStepData = {
        id: string;
        action: string;
        config: Record<string, any>;
        type: 'action';
      };
      export type AutomationCatalogData = {
        triggers: App.Data.Automations.CatalogTriggerData[];
        field_groups: App.Data.Automations.CatalogFieldGroupData[];
        operators: Record<string, App.Data.Automations.CatalogOperatorData[]>;
        actions: App.Data.Automations.CatalogActionData[];
        placeholders: App.Data.Automations.CatalogPlaceholderData[];
      };
      export type AutomationData = {
        id: string;
        desk_id: string | null;
        name: string;
        description: string | null;
        enabled: boolean;
        has_webhook_token: boolean;
        position: number;
        trigger_type: string;
        definition: App.Data.Automations.AutomationDefinitionData;
        schema_version: number;
        created_by: string | null;
        updated_by: string | null;
        created_at: string | null;
        updated_at: string | null;
        last_run?: App.Data.Automations.AutomationRunSummaryData | null;
        runs_last24h?: number;
      };
      export type AutomationDefinitionData = {
        trigger: App.Data.Automations.TriggerData;
        steps: (
          | App.Data.Automations.ConditionStepData
          | App.Data.Automations.ActionStepData
          | App.Data.Automations.DelayStepData
          | App.Data.Automations.PathsStepData
          | App.Data.Automations.StopStepData
        )[];
      };
      export type AutomationRunData = {
        id: string;
        automation_id: string;
        automation_name?: string;
        ticket_id: string | null;
        ticket_number?: number | null;
        ticket_subject?: string | null;
        ticket_event_id: string | null;
        parent_run_id: string | null;
        depth: number;
        status: App.Enums.AutomationRunStatus;
        log: Record<string, any>[];
        error: string | null;
        resume_at: string | null;
        definition?: App.Data.Automations.AutomationDefinitionData;
        payload?: Record<string, any> | null;
        created_at: string | null;
        updated_at: string | null;
      };
      export type AutomationRunSummaryData = {
        id: string;
        status: App.Enums.AutomationRunStatus;
        created_at: string | null;
      };
      export type AutomationTemplateData = {
        key: string;
        name: string;
        description: string;
        category: string;
        trigger_type: string;
        definition: App.Data.Automations.AutomationDefinitionData;
      };
      export type AutomationTestResultData = {
        trigger_matched: boolean | null;
        trigger_note: string | null;
        matched: boolean;
        stopped: boolean;
        log: Record<string, any>[];
        error: string | null;
      };
      export type AutomationWebhookTokenData = {
        url: string;
        token: string;
      };
      export type BranchData = {
        name: string;
        condition: App.Data.Automations.ConditionGroupData;
        steps: (
          | App.Data.Automations.ConditionStepData
          | App.Data.Automations.ActionStepData
          | App.Data.Automations.DelayStepData
          | App.Data.Automations.PathsStepData
          | App.Data.Automations.StopStepData
        )[];
      };
      export type CatalogActionData = {
        key: string;
        label: string;
        config_type: string;
        config_rules: Record<string, string[]>;
        config_options: Record<
          string,
          App.Data.Automations.CatalogOptionData[]
        >;
      };
      export type CatalogFieldData = {
        key: string;
        label: string;
        type: App.Automations.FieldType;
        options: App.Data.Automations.CatalogOptionData[];
        settable: boolean;
      };
      export type CatalogFieldGroupData = {
        group: App.Automations.FieldGroup;
        label: string;
        fields: App.Data.Automations.CatalogFieldData[];
      };
      export type CatalogOperatorData = {
        value: App.Automations.Operator;
        label: string;
        takes_value: boolean;
      };
      export type CatalogOptionData = {
        value: string;
        label: string;
      };
      export type CatalogPlaceholderData = {
        key: string;
        label: string;
      };
      export type CatalogTriggerData = {
        key: string;
        label: string;
        field_groups: App.Automations.FieldGroup[];
        config_rules: Record<string, string[]>;
        config_options: Record<
          string,
          App.Data.Automations.CatalogOptionData[]
        >;
        placeholders: App.Data.Automations.CatalogPlaceholderData[];
      };
      export type ConditionGroupData = {
        match: App.Automations.MatchMode;
        rules: (
          | App.Data.Automations.RuleData
          | App.Data.Automations.ConditionGroupData
        )[];
        type: 'group';
      };
      export type ConditionNodeData = {
        type: string;
      };
      export type ConditionStepData = {
        id: string;
        match: App.Automations.MatchMode;
        rules: (
          | App.Data.Automations.RuleData
          | App.Data.Automations.ConditionGroupData
        )[];
        type: 'condition';
      };
      export type CreateAutomationData = {
        name: string;
        description?: string | null;
        desk_id?: string | null;
        enabled?: boolean;
        template?: string | null;
        definition: App.Data.Automations.AutomationDefinitionData;
      };
      export type DelayForData = {
        amount: number;
        unit: App.Automations.DelayUnit;
        business_schedule_id: string | null;
      };
      export type DelayStepData = {
        id: string;
        for: App.Data.Automations.DelayForData | null;
        until: App.Data.Automations.DelayUntilData | null;
        cancel_on: string[];
        type: 'delay';
      };
      export type DelayUntilData = {
        field: string;
        offset: number;
      };
      export type ListAutomationRunsData = {
        status?: App.Enums.AutomationRunStatus;
        ticket_id?: string;
        ticket_number?: number;
        from?: string;
        to?: string;
      };
      export type PathsStepData = {
        id: string;
        branches: App.Data.Automations.BranchData[];
        else: (
          | App.Data.Automations.ConditionStepData
          | App.Data.Automations.ActionStepData
          | App.Data.Automations.DelayStepData
          | App.Data.Automations.PathsStepData
          | App.Data.Automations.StopStepData
        )[];
        type: 'paths';
      };
      export type ReceiveAutomationWebhookData = {
        payload: Record<string, any>;
      };
      export type ReorderAutomationsData = {
        ids: string[];
      };
      export type RuleData = {
        field: string;
        operator: App.Automations.Operator;
        value: any;
        type: 'rule';
      };
      export type SetAutomationEnabledData = {
        enabled: boolean;
      };
      export type StepData = {
        id: string;
        type: string;
      };
      export type StopStepData = {
        id: string;
        type: 'stop';
      };
      export type TestAutomationData = {
        definition: App.Data.Automations.AutomationDefinitionData;
        desk_id?: string | null;
        ticket_id: string;
        ticket_event_id?: string | null;
        sample_payload?: Record<string, any> | null;
      };
      export type TriggerData = {
        type: string;
        config: Record<string, any>;
      };
      export type UpdateAutomationData = {
        name?: string;
        description?: string | null;
        desk_id?: string | null;
        enabled?: boolean;
        definition: App.Data.Automations.AutomationDefinitionData;
      };
      export type WebhookReceivedData = {
        run_id: string;
      };
      namespace Actions {
        export type ActionConfigData = object;
        export type AddNoteConfigData = {
          body: string;
        };
        export type ApplyMacroConfigData = {
          macro_id: string;
        };
        export type AssignConfigData = {
          mode: App.Automations.AssignMode;
          user_ids: string[] | null;
          desk_id: string | null;
        };
        export type MoveDeskConfigData = {
          desk_id: string;
        };
        export type NotifyConfigData = {
          recipients: App.Automations.NotifyRecipients;
          subject: string;
          body: string;
          user_ids: string[] | null;
        };
        export type SendReplyConfigData = {
          body: string;
        };
        export type SendWebhookConfigData = {
          method: string;
          url: string;
          headers:
            | {
                name: string;
                value: string;
              }[]
            | null;
          body: string | null;
        };
        export type SetFieldConfigData = {
          field: string;
          value: any;
        };
        export type SetReminderConfigData = {
          after: App.Data.Automations.DelayForData;
          note: string | null;
        };
        export type SetSlaPolicyConfigData = {
          sla_policy_id: string | null;
        };
        export type TagsConfigData = {
          mode: App.Automations.TagsMode;
          names: string[];
        };
      }
    }
    namespace BusinessSchedules {
      export type BusinessScheduleData = {
        id: string;
        name: string;
        timezone: string;
        weekly_hours: Record<string, string[][]>;
        holidays: string[];
        created_at: string | null;
        updated_at: string | null;
      };
      export type CreateBusinessScheduleData = {
        name: string;
        timezone: string;
        weekly_hours: Record<string, string[][]>;
        holidays?: string[];
      };
      export type UpdateBusinessScheduleData = {
        name?: string;
        timezone?: string;
        weekly_hours?: Record<string, string[][]>;
        holidays?: string[];
      };
    }
    namespace Contacts {
      export type ContactData = {
        id: string;
        name: string;
        email: string;
        tickets_count?: number;
      };
      export type CreateContactData = {
        name: string;
        email: string;
      };
      export type EraseContactData = {
        confirm_email: string;
      };
      export type SearchContactsData = {
        search?: string | null;
      };
      export type UpdateContactData = {
        name?: string;
        email?: string;
      };
    }
    namespace CustomFields {
      export type CreateCustomFieldData = {
        name: string;
        type: App.Enums.CustomFieldType;
        options?: string[];
        is_required?: boolean;
        position?: number;
      };
      export type CustomFieldData = {
        id: string;
        name: string;
        type: App.Enums.CustomFieldType;
        type_label: string;
        options: string[] | null;
        is_required: boolean;
        position: number;
        desks?: App.Data.Desks.DeskData[];
      };
      export type UpdateCustomFieldData = {
        name?: string;
        type?: App.Enums.CustomFieldType;
        options?: string[] | null;
        is_required?: boolean;
        position?: number;
      };
    }
    namespace Desks {
      export type CreateDeskData = {
        name: string;
        slug: string;
      };
      export type DeskData = {
        id: string;
        name: string;
        slug: string;
        assignment_strategy: App.Enums.AssignmentStrategy;
        sla_policy_id: string | null;
        custom_fields?: App.Data.CustomFields.CustomFieldData[];
        members?: App.Data.Desks.DeskMemberData[];
        archived_at: string | null;
      };
      export type DeskMemberData = {
        id: string;
        name: string;
        email: string;
        role: App.Enums.DeskRole | null;
        in_rotation: boolean;
      };
      export type SetDeskMemberData = {
        role: App.Enums.DeskRole;
        in_rotation?: boolean;
      };
      export type SyncDeskCustomFieldsData = {
        field_ids: string[];
      };
      export type UpdateDeskData = {
        name?: string;
        slug?: string;
        assignment_strategy?: App.Enums.AssignmentStrategy;
        sla_policy_id?: string | null;
      };
    }
    namespace Exports {
      export type ExportData = {
        id: string;
        type: App.Enums.ExportType;
        status: App.Enums.ExportStatus;
        subject_id: string | null;
        subject_name: string | null;
        requested_by: string | null;
        requested_by_name: string | null;
        size: number | null;
        download_url: string | null;
        completed_at: string | null;
        expires_at: string;
        created_at: string;
      };
    }
    namespace Install {
      export type ServiceCheckData = {
        name: string;
        ok: boolean;
        detail: string;
      };
    }
    namespace Macros {
      export type AppliedMacroData = {
        body: string | null;
        is_internal: boolean;
      };
      export type CreateMacroData = {
        name: string;
        scope: App.Enums.MacroScope;
        desk_id?: string;
        body?: string | null;
        body_is_internal?: boolean;
        actions?: Record<string, any>;
      };
      export type MacroActionsData = {
        status: App.Enums.TicketStatus | null;
        priority: App.Enums.TicketPriority | null;
        add_tags: string[];
        remove_tags: string[];
        assign_to_me: boolean;
      };
      export type MacroData = {
        id: string;
        name: string;
        scope: App.Enums.MacroScope;
        user_id: string | null;
        desk_id: string | null;
        body: string | null;
        body_is_internal: boolean;
        actions: App.Data.Macros.MacroActionsData;
        created_at: string | null;
        updated_at: string | null;
      };
      export type UpdateMacroData = {
        name?: string;
        body?: string | null;
        body_is_internal?: boolean;
        actions?: Record<string, any>;
      };
    }
    namespace Mailboxes {
      export type CreateMailboxData = {
        driver: App.Enums.MailboxDriver;
        name: string;
        address: string;
        default_desk_id: string;
        routing?: {
          address: string;
          desk_id: string;
        }[];
        signature?: string | null;
        settings: Record<string, any>;
      };
      export type MailboxData = {
        id: string;
        driver: App.Enums.MailboxDriver;
        driver_label: string;
        name: string;
        address: string;
        inbound_address: string | null;
        default_desk_id: string;
        routing: App.Data.Mailboxes.MailboxRoutingRuleData[];
        signature: string | null;
        settings?: {
          imap: App.Data.Mailboxes.MailboxServerData;
          smtp: App.Data.Mailboxes.MailboxServerData;
        };
        receives_by: 'webhook' | 'push' | 'watch' | 'poll';
        polled_at: string | null;
        last_error: string | null;
        created_at: string | null;
      };
      export type MailboxRoutingRuleData = {
        address: string;
        desk_id: string;
      };
      export type MailboxServerData = {
        host: string;
        port: number;
        encryption: 'ssl' | 'tls' | 'starttls';
        username: string;
        folder?: string;
      };
      export type UpdateMailboxData = {
        name?: string;
        address?: string;
        default_desk_id?: string;
        routing?: {
          address: string;
          desk_id: string;
        }[];
        signature?: string | null;
        settings?: Record<string, any>;
      };
    }
    namespace Notifications {
      export type MarkNotificationsReadData = {
        ids: string[] | null;
      };
      export type NotificationData = {
        id: string;
        type: App.Enums.NotificationType;
        data: Record<string, unknown>;
        read_at: string | null;
        created_at: string | null;
      };
      export type NotificationPreferenceData = {
        type: App.Enums.NotificationType;
        label: string;
        description: string;
        channels: Record<string, boolean>;
      };
      export type UpdateNotificationPreferencesData = {
        preferences: Record<string, Record<string, boolean>>;
      };
    }
    namespace Portal {
      export type OpenPortalTicketData = {
        subject: string;
        body: string;
        attachments?: File[];
      };
      export type PortalDeskData = {
        id: string;
        name: string;
      };
      export type PortalTicketData = {
        id: string;
        number: number;
        subject: string;
        status: App.Enums.TicketStatus;
        status_label: string;
        desk_name: string;
        satisfaction_rating?: App.Data.Tickets.SatisfactionRatingData | null;
        created_at: string | null;
        updated_at: string | null;
      };
      export type SendPortalMagicLinkData = {
        email: string;
      };
    }
    namespace Realtime {
      export type RealtimeData = {
        key: string;
        host: string;
        port: number;
        scheme: string;
      };
    }
    namespace Reports {
      export type AgentRowData = {
        agent: App.Data.Users.UserData;
        public_replies: number;
        internal_notes: number;
        tickets_solved: number;
        open_assigned: number;
        median_reply: number | null;
        median_first_response: number | null;
      };
      export type AgentsReportData = {
        period: App.Data.Reports.ReportPeriodData;
        agents: App.Data.Reports.AgentRowData[];
      };
      export type BreakdownData = {
        key: string;
        label: string;
        count: number;
      };
      export type HeatmapCellData = {
        weekday: number;
        hour: number;
        count: number;
      };
      export type KpiData = {
        value: number | null;
        previous: number | null;
      };
      export type OverviewReportData = {
        period: App.Data.Reports.ReportPeriodData;
        created: App.Data.Reports.KpiData;
        resolved: App.Data.Reports.KpiData;
        reopened: App.Data.Reports.KpiData;
        backlog: App.Data.Reports.BreakdownData[];
        volume: App.Data.Reports.SeriesPointData[];
        heatmap: App.Data.Reports.HeatmapCellData[];
        by_source: App.Data.Reports.BreakdownData[];
        by_desk: App.Data.Reports.BreakdownData[];
      };
      export type ReportFiltersData = {
        from?: string;
        to?: string;
        desk_ids?: string[];
        basis?: App.Enums.ReportBasis;
      };
      export type ReportPeriodData = {
        from: string;
        to: string;
        previous_from: string;
        previous_to: string;
        timezone: string;
        bucket: string;
        basis: App.Enums.ReportBasis;
        desk_ids: string[];
      };
      export type ResolutionReportData = {
        period: App.Data.Reports.ReportPeriodData;
        median_resolution: App.Data.Reports.KpiData;
        average_resolution: App.Data.Reports.KpiData;
        reopen_rate: App.Data.Reports.KpiData;
        distribution: App.Data.Reports.BreakdownData[];
        trend: App.Data.Reports.SeriesPointData[];
      };
      export type ResponsesReportData = {
        period: App.Data.Reports.ReportPeriodData;
        median_first_response: App.Data.Reports.KpiData;
        average_first_response: App.Data.Reports.KpiData;
        median_reply: App.Data.Reports.KpiData;
        average_reply: App.Data.Reports.KpiData;
        first_response_within_hour: App.Data.Reports.KpiData;
        first_response_within_four_hours: App.Data.Reports.KpiData;
        first_response_within_day: App.Data.Reports.KpiData;
        first_response_distribution: App.Data.Reports.BreakdownData[];
        trend: App.Data.Reports.SeriesPointData[];
      };
      export type SatisfactionCommentData = {
        ticket_id: string;
        ticket_number: number;
        rating: App.Enums.SatisfactionScore;
        comment: string;
        created_at: string | null;
      };
      export type SatisfactionDeskData = {
        desk_id: string;
        name: string;
        good: number;
        bad: number;
        score: number;
      };
      export type SatisfactionReportData = {
        period: App.Data.Reports.ReportPeriodData;
        good: App.Data.Reports.KpiData;
        bad: App.Data.Reports.KpiData;
        score: App.Data.Reports.KpiData;
        response_rate: App.Data.Reports.KpiData;
        trend: App.Data.Reports.SeriesPointData[];
        by_desk: App.Data.Reports.SatisfactionDeskData[];
        comments: App.Data.Reports.SatisfactionCommentData[];
      };
      export type SeriesPointData = {
        date: string;
        values: Record<string, number | null>;
      };
      export type SlaMetricReportData = {
        metric: App.Enums.SlaMetric;
        met: number;
        breached: number;
        in_progress: number;
        percent_met: App.Data.Reports.KpiData;
        by_priority: App.Data.Reports.SlaPriorityData[];
      };
      export type SlaPriorityData = {
        priority: App.Enums.TicketPriority;
        met: number;
        breached: number;
        percent_met: number | null;
      };
      export type SlaReportData = {
        period: App.Data.Reports.ReportPeriodData;
        metrics: App.Data.Reports.SlaMetricReportData[];
        breaches: App.Data.Reports.SeriesPointData[];
      };
    }
    namespace SavedViews {
      export type CreateSavedViewData = {
        name: string;
        filters: Record<string, any>;
        is_shared?: boolean;
      };
      export type SavedViewData = {
        id: string;
        user_id: string;
        owner?: App.Data.Users.UserData;
        name: string;
        filters: Record<string, string | string[] | boolean>;
        is_shared: boolean;
        created_at: string | null;
        updated_at: string | null;
      };
      export type UpdateSavedViewData = {
        name?: string;
        filters?: Record<string, any>;
        is_shared?: boolean;
      };
    }
    namespace SlaPolicies {
      export type CreateSlaPolicyData = {
        name: string;
        targets: Record<string, Record<string, number>>;
        business_schedule_id?: string | null;
        warning_percent?: number;
      };
      export type SlaPolicyData = {
        id: string;
        name: string;
        business_schedule_id: string | null;
        warning_percent: number;
        targets: Record<string, Record<string, number>>;
        business_schedule?: App.Data.BusinessSchedules.BusinessScheduleData | null;
        created_at: string | null;
        updated_at: string | null;
      };
      export type UpdateSlaPolicyData = {
        name?: string;
        targets?: Record<string, Record<string, number>>;
        business_schedule_id?: string | null;
        warning_percent?: number;
      };
    }
    namespace Tags {
      export type TagData = {
        id: string;
        name: string;
      };
    }
    namespace Tickets {
      export type AddTicketAttachmentData = {
        file: File;
      };
      export type AddTicketCommentData = {
        body: string;
        is_internal?: boolean;
        message_id?: string | null;
        attachments?: File[];
        mentioned_user_ids?: string[];
        last_seen_comment_id?: string | null;
      };
      export type AttachmentData = {
        id: string;
        ticket_id: string;
        ticket_comment_id: string | null;
        filename: string;
        mime_type: string;
        size: number;
        checksum: string;
        uploader_type: string;
        uploader_id: string;
        download_url: string;
        created_at: string | null;
      };
      export type BulkUpdateTicketsData = {
        ticket_ids: string[];
        status?: App.Enums.TicketStatus;
        priority?: App.Enums.TicketPriority;
        assignee_ids?: string[];
        add_tag?: string;
        desk_id?: string;
        delete?: boolean;
      };
      export type CreateTicketData = {
        subject: string;
        status?: App.Enums.TicketStatus;
        priority?: App.Enums.TicketPriority;
        due_at?: string | null;
        requester_id?: string;
        requester?: {
          name: string;
          email: string;
        };
        assignee_ids?: string[];
        mailbox_id?: string | null;
        message_id?: string | null;
        body?: string | null;
        attachments?: File[];
        custom_fields?: Record<string, any>;
      };
      export type LookupTicketsData = {
        search?: string | null;
      };
      export type RateTicketData = {
        rating: App.Enums.SatisfactionScore;
        comment?: string | null;
      };
      export type SatisfactionRatingData = {
        id: string;
        ticket_id: string;
        contact_id: string;
        rating: App.Enums.SatisfactionScore;
        rating_label: string;
        comment: string | null;
        created_at: string | null;
        updated_at: string | null;
      };
      export type SearchTicketsData = {
        search?: string | null;
        desk_id?: string | string[];
        status?: string | string[];
        priority?: string | string[];
        tag?: string | string[];
        assignee_id?: string;
        requester_id?: string;
        deleted?: boolean;
        sort?: string;
      };
      export type SetTicketReminderData = {
        remind_at: string;
        note?: string | null;
        for_assignees?: boolean;
      };
      export type SyncTicketAssigneesData = {
        user_ids: string[];
      };
      export type SyncTicketTagsData = {
        names: string[];
      };
      export type TicketCommentData = {
        id: string;
        ticket_id: string;
        body: string;
        is_internal: boolean;
        author_type: string;
        author_id: string;
        author_name?: string | null;
        attachments?: App.Data.Tickets.AttachmentData[];
        edited_at: string | null;
        created_at: string | null;
      };
      export type TicketData = {
        id: string;
        number: number;
        subject: string;
        status: App.Enums.TicketStatus;
        status_label: string;
        priority: App.Enums.TicketPriority;
        priority_label: string;
        source: App.Enums.TicketSource;
        desk_id: string;
        sla_policy_id: string | null;
        desk?: App.Data.Desks.DeskData;
        due_at: string | null;
        custom_fields: Record<
          string,
          string | number | boolean | string[] | null
        >;
        requester?: App.Data.Contacts.ContactData;
        assignees?: App.Data.Users.UserData[];
        tags?: App.Data.Tags.TagData[];
        sla_targets?: App.Data.Tickets.TicketSlaTargetData[];
        satisfaction_rating?: App.Data.Tickets.SatisfactionRatingData | null;
        created_at: string | null;
        updated_at: string | null;
        deleted_at: string | null;
      };
      export type TicketEventData = {
        id: string;
        ticket_id?: string;
        ticket_number?: number;
        type: App.Enums.TicketEventType;
        description: string;
        actor_name: string;
        actor_type: string | null;
        actor_id: string | null;
        automation_run_id: string | null;
        properties: Record<string, any>;
        created_at: string;
      };
      export type TicketReminderData = {
        id: string;
        ticket_id: string;
        user_id: string | null;
        for_assignees: boolean;
        created_by: string | null;
        remind_at: string;
        note: string | null;
        sent_at: string | null;
      };
      export type TicketSlaTargetData = {
        metric: App.Enums.SlaMetric;
        metric_label: string;
        started_at: string;
        due_at: string | null;
        paused: boolean;
        warned_at: string | null;
        breached_at: string | null;
        achieved_at: string | null;
      };
      export type UpdateTicketCommentData = {
        body: string;
      };
      export type UpdateTicketData = {
        subject?: string;
        status?: App.Enums.TicketStatus;
        priority?: App.Enums.TicketPriority;
        due_at?: string | null;
        custom_fields?: Record<string, any>;
        sla_policy_id?: string | null;
      };
    }
    namespace Users {
      export type CurrentUserData = {
        id: string;
        name: string;
        email: string;
        email_verified_at: string | null;
        timezone: string | null;
      };
      export type UpdateProfileData = {
        name?: string;
        email?: string;
        timezone?: string;
      };
      export type UserData = {
        id: string;
        name: string;
        email: string;
      };
    }
    namespace Workspaces {
      export type CreateWorkspaceData = {
        name: string;
        subdomain: string;
      };
      export type CreateWorkspaceOwnerData = {
        name: string;
        email: string;
        password: string;
      };
      export type ImportWorkspaceData = {
        file: File;
      };
      export type ProvisionedWorkspaceData = {
        id: string;
        name: string;
        subdomain: string;
        url: string;
        has_owner: boolean;
        setup_url: string | null;
      };
      export type SetAwayData = {
        away: boolean;
      };
      export type UpdateProvisionedWorkspaceData = {
        name?: string;
        subdomain?: string;
      };
      export type UpdateWorkspaceData = {
        name: string;
      };
      export type UpdateWorkspaceSettingsData = {
        mail_notification_mailbox_id?: string | null;
        mail_new_tickets_per_sender_per_hour?: number | null;
        attachments_max_size_kb?: number | null;
        attachments_blocked_extensions?: string[] | null;
        automations_allow_private_urls?: boolean | null;
        portal_enabled?: boolean | null;
        portal_access?: App.Enums.PortalAccess | null;
        surveys_enabled?: boolean | null;
        retention_resolved_ticket_days?: number | null;
        retention_trash_days?: number | null;
      };
      export type WorkspaceData = {
        id: string;
        name: string;
        subdomain: string;
        is_admin: boolean;
        away_at: string | null;
        settings?: App.Data.Workspaces.WorkspaceSettings;
      };
      export type WorkspaceSettings = {
        mail_notification_mailbox_id: string | null;
        mail_new_tickets_per_sender_per_hour: number;
        attachments_max_size_kb: number;
        attachments_blocked_extensions: string[];
        automations_allow_private_urls: boolean;
        portal_enabled: boolean;
        portal_access: App.Enums.PortalAccess;
        surveys_enabled: boolean;
        retention_resolved_ticket_days: number | null;
        retention_trash_days: number;
      };
    }
  }
  namespace Enums {
    export type AssignmentStrategy = 'manual' | 'round_robin' | 'load_balanced';
    export type AuditAction =
      | 'workspace_updated'
      | 'workspace_settings_updated'
      | 'desk_created'
      | 'desk_updated'
      | 'desk_archived'
      | 'desk_restored'
      | 'desk_member_set'
      | 'desk_member_removed'
      | 'desk_custom_fields_synced'
      | 'custom_field_created'
      | 'custom_field_updated'
      | 'custom_field_deleted'
      | 'mailbox_created'
      | 'mailbox_updated'
      | 'mailbox_deleted'
      | 'macro_created'
      | 'macro_updated'
      | 'macro_deleted'
      | 'automation_created'
      | 'automation_updated'
      | 'automation_deleted'
      | 'automation_enabled'
      | 'automation_disabled'
      | 'automation_webhook_token_rotated'
      | 'sla_policy_created'
      | 'sla_policy_updated'
      | 'sla_policy_deleted'
      | 'business_schedule_created'
      | 'business_schedule_updated'
      | 'business_schedule_deleted'
      | 'api_token_created'
      | 'api_token_revoked'
      | 'contact_export_requested'
      | 'workspace_export_requested'
      | 'export_downloaded'
      | 'contact_erased'
      | 'workspace_import_requested'
      | 'workspace_imported'
      | 'workspace_import_failed';
    export type AutomationRunStatus =
      | 'running'
      | 'waiting'
      | 'completed'
      | 'skipped'
      | 'failed'
      | 'cancelled';
    export type CustomFieldType =
      | 'text'
      | 'textarea'
      | 'number'
      | 'decimal'
      | 'date'
      | 'checkbox'
      | 'select'
      | 'multi_select';
    export type DeskRole = 'lead' | 'agent';
    export type ExportStatus = 'pending' | 'completed' | 'failed';
    export type ExportType = 'contact' | 'workspace';
    export type MacroScope = 'personal' | 'workspace' | 'desk';
    export type MailboxDriver = 'hosted' | 'imap' | 'google' | 'microsoft';
    export type NotificationChannel = 'database' | 'mail';
    export type NotificationType =
      | 'new_ticket'
      | 'assigned'
      | 'requester_replied'
      | 'ticket_updated'
      | 'mentioned'
      | 'reminder_due'
      | 'sla_warning'
      | 'sla_breached'
      | 'automation';
    export type PortalAccess = 'anyone' | 'contacts';
    export type Report =
      | 'overview'
      | 'responses'
      | 'resolution'
      | 'agents'
      | 'sla'
      | 'satisfaction';
    export type ReportBasis = 'calendar' | 'business';
    export type SatisfactionScore = 'good' | 'bad';
    export type SlaMetric = 'first_response' | 'resolution';
    export type TicketEventType =
      | 'created'
      | 'subject_changed'
      | 'status_changed'
      | 'priority_changed'
      | 'due_date_changed'
      | 'desk_changed'
      | 'sla_policy_changed'
      | 'custom_field_changed'
      | 'assignee_added'
      | 'assignee_removed'
      | 'tag_added'
      | 'tag_removed'
      | 'comment_added'
      | 'internal_note_added'
      | 'comment_edited'
      | 'comment_deleted'
      | 'attachment_added'
      | 'attachment_removed'
      | 'reminder_set'
      | 'sla_warning'
      | 'sla_breached'
      | 'email_bounced'
      | 'satisfaction_rated'
      | 'deleted'
      | 'restored';
    export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';
    export type TicketSource =
      | 'email'
      | 'web'
      | 'api'
      | 'webhook'
      | 'automation'
      | 'portal';
    export type TicketStatus =
      | 'open'
      | 'pending'
      | 'on_hold'
      | 'solved'
      | 'closed';
    export type WorkspaceRole = 'owner' | 'admin' | 'agent';
  }
}
declare namespace Illuminate {
  export type CursorPaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
      url: string | null;
      label: string;
      active: boolean;
    }[];
    meta: {
      path: string;
      per_page: number;
      next_cursor: string | null;
      next_page_url: string | null;
      prev_cursor: string | null;
      prev_page_url: string | null;
    };
  };
  export type CursorPaginatorInterface<TKey, TValue> =
    Illuminate.CursorPaginator<TKey, TValue>;
  export type LengthAwarePaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
      url: string | null;
      label: string;
      active: boolean;
    }[];
    meta: {
      total: number;
      current_page: number;
      first_page_url: string;
      from: number | null;
      last_page: number;
      last_page_url: string;
      next_page_url: string | null;
      path: string;
      per_page: number;
      prev_page_url: string | null;
      to: number | null;
    };
  };
  export type LengthAwarePaginatorInterface<TKey, TValue> =
    Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
  namespace LaravelData {
    export type CursorPaginatedDataCollection<TKey, TValue> =
      Illuminate.CursorPaginator<TKey, TValue>;
    export type PaginatedDataCollection<TKey, TValue> =
      Illuminate.LengthAwarePaginator<TKey, TValue>;
  }
}

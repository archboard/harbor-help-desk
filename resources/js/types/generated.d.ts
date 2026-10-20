declare namespace App {
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
    namespace Auth {
      export type ResetUserPasswordData = {
        password: string;
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
        custom_fields?: App.Data.CustomFields.CustomFieldData[];
        members?: App.Data.Desks.DeskMemberData[];
        archived_at: string | null;
      };
      export type DeskMemberData = {
        id: string;
        name: string;
        email: string;
        role: App.Enums.DeskRole | null;
      };
      export type SetDeskMemberData = {
        role: App.Enums.DeskRole;
      };
      export type SyncDeskCustomFieldsData = {
        field_ids: string[];
      };
      export type UpdateDeskData = {
        name?: string;
        slug?: string;
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
    namespace Realtime {
      export type RealtimeData = {
        key: string;
        host: string;
        port: number;
        scheme: string;
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
        desk_id: string;
        desk?: App.Data.Desks.DeskData;
        due_at: string | null;
        custom_fields: Record<
          string,
          string | number | boolean | string[] | null
        >;
        requester?: App.Data.Contacts.ContactData;
        assignees?: App.Data.Users.UserData[];
        tags?: App.Data.Tags.TagData[];
        created_at: string | null;
        updated_at: string | null;
        deleted_at: string | null;
      };
      export type TicketEventData = {
        id: string;
        type: App.Enums.TicketEventType;
        description: string;
        actor_name: string;
        actor_type: string | null;
        actor_id: string | null;
        properties: Record<string, any>;
        created_at: string;
      };
      export type TicketReminderData = {
        id: string;
        ticket_id: string;
        user_id: string | null;
        for_assignees: boolean;
        created_by: string;
        remind_at: string;
        note: string | null;
        sent_at: string | null;
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
      export type UserData = {
        id: string;
        name: string;
        email: string;
      };
    }
    namespace Workspaces {
      export type UpdateWorkspaceData = {
        name: string;
      };
      export type UpdateWorkspaceSettingsData = {
        mail_notification_mailbox_id?: string | null;
        mail_new_tickets_per_sender_per_hour?: number | null;
        attachments_max_size_kb?: number | null;
        attachments_blocked_extensions?: string[] | null;
      };
      export type WorkspaceData = {
        id: string;
        name: string;
        subdomain: string;
        is_admin: boolean;
        settings?: App.Data.Workspaces.WorkspaceSettings;
      };
      export type WorkspaceSettings = {
        mail_notification_mailbox_id: string | null;
        mail_new_tickets_per_sender_per_hour: number;
        attachments_max_size_kb: number;
        attachments_blocked_extensions: string[];
      };
    }
  }
  namespace Enums {
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
    export type MailboxDriver = 'hosted' | 'imap' | 'google' | 'microsoft';
    export type NotificationChannel = 'database' | 'mail';
    export type NotificationType =
      | 'new_ticket'
      | 'assigned'
      | 'requester_replied'
      | 'ticket_updated'
      | 'mentioned'
      | 'reminder_due';
    export type TicketEventType =
      | 'created'
      | 'subject_changed'
      | 'status_changed'
      | 'priority_changed'
      | 'due_date_changed'
      | 'desk_changed'
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
      | 'email_bounced'
      | 'deleted'
      | 'restored';
    export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';
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

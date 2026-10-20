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
    }
    namespace Contacts {
      export type ContactData = {
        id: string;
        name: string;
        email: string;
        tickets_count?: number;
      };
    }
    namespace CustomFields {
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
    }
    namespace Desks {
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
    }
    namespace Mailboxes {
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
    }
    namespace SavedViews {
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
    }
    namespace Tags {
      export type TagData = {
        id: string;
        name: string;
      };
    }
    namespace Tickets {
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
      export type WorkspaceData = {
        id: string;
        name: string;
        subdomain: string;
        is_admin: boolean;
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

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

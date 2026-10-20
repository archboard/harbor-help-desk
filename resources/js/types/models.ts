export type TicketStatus = 'open' | 'pending' | 'on_hold' | 'solved' | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

/** A ticket's value for one custom field, by the field's type. */
export type CustomFieldValue = string | number | boolean | string[] | null;

export type Ticket = {
  id: string;
  number: number;
  subject: string;
  status: TicketStatus;
  status_label: string;
  priority: TicketPriority;
  priority_label: string;
  desk_id: string;
  desk?: App.Data.Desks.DeskData;
  due_at: string | null;
  custom_fields: Record<string, CustomFieldValue>;
  requester?: App.Data.Contacts.ContactData;
  assignees?: App.Data.Users.UserData[];
  tags?: App.Data.Tags.TagData[];
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

/** A paginated resource collection, as `Inertia::scroll()` sends it. */
export type Paginated<T> = {
  data: T[];
};

export type Attachment = {
  id: string;
  ticket_id: string;
  ticket_comment_id: string | null;
  filename: string;
  mime_type: string;
  size: number;
  uploader_type: string | null;
  uploader_id: string | null;
  download_url: string;
  created_at: string | null;
};

export type TicketComment = {
  id: string;
  ticket_id: string;
  body: string;
  is_internal: boolean;
  author_type: string | null;
  author_id: string | null;
  author_name?: string | null;
  attachments?: Attachment[];
  edited_at: string | null;
  created_at: string | null;
};

export type TicketEvent = {
  id: string;
  type: string;
  description: string;
  actor_name: string;
  properties: Record<string, unknown> | null;
  created_at: string;
};

export type TicketReminder = {
  id: string;
  user_id: string | null;
  for_assignees: boolean;
  created_by: string;
  remind_at: string;
  note: string | null;
};

export type MailboxDriver = 'hosted' | 'imap' | 'google' | 'microsoft';

export type MailboxRoutingRule = {
  address: string;
  desk_id: string;
};

export type MailboxServer = {
  host: string;
  port: number;
  encryption: 'ssl' | 'tls' | 'starttls';
  username: string;
  folder?: string;
};

export type Mailbox = {
  id: string;
  driver: MailboxDriver;
  driver_label: string;
  name: string;
  address: string;
  /** Hosted only: the address tenants forward their own mail to. */
  inbound_address: string | null;
  default_desk_id: string;
  routing: MailboxRoutingRule[];
  signature: string | null;
  /** IMAP only, never with passwords. */
  settings?: { imap: MailboxServer; smtp: MailboxServer };
  receives_by: 'webhook' | 'push' | 'watch' | 'poll';
  polled_at: string | null;
  last_error: string | null;
  created_at: string | null;
};

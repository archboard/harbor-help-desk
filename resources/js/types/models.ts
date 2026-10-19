export type Desk = {
  id: string;
  name: string;
  slug: string;
  archived_at: string | null;
  custom_fields?: CustomField[];
  members?: DeskMember[];
};

export type CustomFieldType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'decimal'
  | 'date'
  | 'checkbox'
  | 'select'
  | 'multi_select';

export type CustomField = {
  id: string;
  name: string;
  type: CustomFieldType;
  type_label: string;
  options: string[] | null;
  is_required: boolean;
  position: number;
  desks?: Desk[];
};

export type DeskRole = 'lead' | 'agent';

export type Agent = {
  id: string;
  name: string;
  email: string;
};

export type DeskMember = Agent & {
  role: DeskRole | null;
};

export type Contact = {
  id: string;
  name: string;
  email: string;
  /** Tickets the viewer can see, when counted. */
  tickets_count?: number;
};

export type Tag = {
  id: string;
  name: string;
};

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
  desk?: Desk;
  due_at: string | null;
  custom_fields: Record<string, CustomFieldValue>;
  requester?: Contact;
  assignees?: Agent[];
  tags?: Tag[];
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

export type SavedView = {
  id: string;
  user_id: string;
  owner?: Agent;
  name: string;
  /** `SearchTickets` filters; the API may store `deleted` as a boolean. */
  filters: Record<string, string | string[] | boolean>;
  is_shared: boolean;
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

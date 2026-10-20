/** A ticket's value for one custom field, by the field's type. */
export type CustomFieldValue = string | number | boolean | string[] | null;

/** A paginated resource collection, as `Inertia::scroll()` sends it. */
export type Paginated<T> = {
  data: T[];
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

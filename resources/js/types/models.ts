export type Desk = {
  id: string;
  name: string;
  slug: string;
  archived_at: string | null;
  custom_fields?: CustomField[];
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
};

export type Tag = {
  id: string;
  name: string;
};

export type TicketStatus = 'open' | 'pending' | 'on_hold' | 'solved' | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

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
  custom_fields: Record<string, unknown>;
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

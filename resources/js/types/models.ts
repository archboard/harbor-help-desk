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

import { formatDate } from '@/lib/dates';

export type TicketColumn = { id: string; label: string };

export const BUILT_IN_COLUMNS: TicketColumn[] = [
  { id: 'number', label: '#' },
  { id: 'subject', label: 'Subject' },
  { id: 'requester', label: 'Requester' },
  { id: 'desk', label: 'Desk' },
  { id: 'status', label: 'Status' },
  { id: 'priority', label: 'Priority' },
  { id: 'assignees', label: 'Assignees' },
  { id: 'tags', label: 'Tags' },
  { id: 'due', label: 'Due' },
  { id: 'created', label: 'Created' },
  { id: 'updated', label: 'Updated' },
];

export const DEFAULT_COLUMNS = [
  'number',
  'subject',
  'requester',
  'desk',
  'status',
  'priority',
  'assignees',
  'updated',
];

/** The id of a custom field's column. */
export function customFieldColumnId(field: { id: string }): string {
  return `cf:${field.id}`;
}

/** Every column a table can show: the built-ins, then one per custom field. */
export function availableColumns(
  customFields: App.Data.CustomFields.CustomFieldData[],
): TicketColumn[] {
  return [
    ...BUILT_IN_COLUMNS,
    ...customFields.map((field) => ({
      id: customFieldColumnId(field),
      label: field.name,
    })),
  ];
}

/** A custom field value as text, by the field's type. */
export function formatCustomField(
  field: App.Data.CustomFields.CustomFieldData,
  value: string | number | boolean | string[] | null | undefined,
): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (field.type === 'checkbox') {
    return value ? 'Yes' : 'No';
  }

  if (field.type === 'date') {
    return formatDate(String(value));
  }

  return Array.isArray(value) ? value.join(', ') || '—' : String(value);
}

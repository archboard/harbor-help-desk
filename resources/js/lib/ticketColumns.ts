import { trans } from 'laravel-vue-i18n';
import { formatDate } from '@/lib/dates';

export type TicketColumn = { id: string; label: string };

/** A function, not a constant, so the labels follow the locale. */
export function builtInColumns(): TicketColumn[] {
  return [
    { id: 'number', label: '#' },
    { id: 'subject', label: trans('Subject') },
    { id: 'requester', label: trans('Requester') },
    { id: 'desk', label: trans('Desk') },
    { id: 'status', label: trans('Status') },
    { id: 'priority', label: trans('Priority') },
    { id: 'assignees', label: trans('Assignees') },
    { id: 'tags', label: trans('Tags') },
    { id: 'due', label: trans('Due') },
    { id: 'created', label: trans('Created') },
    { id: 'updated', label: trans('Updated') },
  ];
}

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
    ...builtInColumns(),
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
    return value ? trans('Yes') : trans('No');
  }

  if (field.type === 'date') {
    return formatDate(String(value));
  }

  return Array.isArray(value) ? value.join(', ') || '—' : String(value);
}

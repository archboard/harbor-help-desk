import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';

function isEmpty(value: unknown): boolean {
  return (
    value === undefined ||
    value === '' ||
    (typeof value === 'object' && Object.keys(value ?? {}).length === 0)
  );
}

/**
 * Ticket list filters as they go in the URL: empty values dropped, booleans as `1`/`0`, lists
 * sorted (one value collapses to a string) and keys sorted, so two queries can be compared as
 * JSON. `custom_fields` is cleaned the same way, one level down.
 */
export function cleanQuery(query: Record<string, unknown>): TicketQuery {
  return Object.fromEntries(
    Object.entries(query)
      .map(([key, value]): [string, unknown] => {
        if (typeof value === 'boolean') {
          return [key, value ? '1' : '0'];
        }

        if (Array.isArray(value)) {
          const sorted = [...value].sort((a, b) => String(a).localeCompare(b));

          return [key, sorted.length === 1 ? sorted[0] : sorted];
        }

        return [
          key,
          value && typeof value === 'object'
            ? cleanQuery(value as Record<string, unknown>)
            : value,
        ];
      })
      .filter(([, value]) => !isEmpty(value))
      .sort(([a], [b]) => a.localeCompare(b)),
  ) as TicketQuery;
}

export function sameQuery(a: TicketQuery, b: TicketQuery): boolean {
  return JSON.stringify(cleanQuery(a)) === JSON.stringify(cleanQuery(b));
}

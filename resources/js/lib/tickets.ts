import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';

/**
 * Ticket list filters as they go in the URL: empty values dropped, booleans as `1`/`0`, keys
 * sorted so two queries can be compared as JSON.
 */
export function cleanQuery(
  query: Record<string, string | string[] | boolean | undefined>,
): TicketQuery {
  return Object.fromEntries(
    Object.entries(query)
      .filter(([, value]) => value !== undefined && value !== '')
      .map(([key, value]) => [
        key,
        typeof value === 'boolean' ? (value ? '1' : '0') : value,
      ])
      .sort(([a], [b]) => String(a).localeCompare(String(b))),
  );
}

export function sameQuery(a: TicketQuery, b: TicketQuery): boolean {
  return JSON.stringify(cleanQuery(a)) === JSON.stringify(cleanQuery(b));
}

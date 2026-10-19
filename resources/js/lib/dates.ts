import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(localizedFormat);
dayjs.extend(relativeTime);

/**
 * The `YYYY-MM-DD HH:mm` local time `AppDatepicker` works with in datetime mode.
 */
const PICKER_FORMAT = 'YYYY-MM-DD HH:mm';

/**
 * A short "3 hours ago" / "in 2 days" for an ISO date.
 */
export function timeAgo(iso: string | null): string {
  return iso ? dayjs(iso).fromNow() : '—';
}

/**
 * A localized date, e.g. "Oct 19, 2026".
 */
export function formatDate(iso: string | null): string {
  return iso ? dayjs(iso).format('ll') : '—';
}

/**
 * A localized date and time, e.g. "Oct 19, 2026 2:30 PM".
 */
export function formatDateTime(iso: string | null): string {
  return iso ? dayjs(iso).format('lll') : '—';
}

/**
 * An ISO date as the local time `AppDatepicker` works with.
 */
export function toLocalDateTime(iso: string | null): string | null {
  return iso ? dayjs(iso).format(PICKER_FORMAT) : null;
}

/**
 * A local time from `AppDatepicker` as an ISO date, so the server reads it in the user's
 * time zone rather than its own. dayjs parses `YYYY-MM-DD HH:mm` as local time.
 */
export function fromLocalDateTime(value: string | null): string | null {
  return value ? dayjs(value).toISOString() : null;
}

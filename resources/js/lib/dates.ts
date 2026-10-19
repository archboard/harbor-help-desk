import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import relativeTime from 'dayjs/plugin/relativeTime';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

dayjs.extend(localizedFormat);
dayjs.extend(relativeTime);
dayjs.extend(utc);
dayjs.extend(timezone);

/**
 * Show dates in the user's saved timezone, or the browser's when they haven't saved one.
 * Every helper below formats and parses in this zone.
 */
export function setDisplayTimezone(zone: string | null): void {
  dayjs.tz.setDefault(zone ?? undefined);
}

/**
 * The browser's IANA timezone, e.g. `America/New_York`.
 */
export function guessTimezone(): string {
  return dayjs.tz.guess();
}

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
  return iso ? dayjs(iso).tz().format('ll') : '—';
}

/**
 * A localized date and time, e.g. "Oct 19, 2026 2:30 PM".
 */
export function formatDateTime(iso: string | null): string {
  return iso ? dayjs(iso).tz().format('lll') : '—';
}

/**
 * An ISO date as the local time `AppDatepicker` works with.
 */
export function toLocalDateTime(iso: string | null): string | null {
  return iso ? dayjs(iso).tz().format(PICKER_FORMAT) : null;
}

/**
 * A local time from `AppDatepicker` as an ISO date, so the server reads it in the user's
 * timezone rather than its own.
 */
export function fromLocalDateTime(value: string | null): string | null {
  return value ? dayjs.tz(value).toISOString() : null;
}

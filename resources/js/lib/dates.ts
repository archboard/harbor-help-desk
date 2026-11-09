import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import relativeTime from 'dayjs/plugin/relativeTime';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

dayjs.extend(localizedFormat);
dayjs.extend(relativeTime);
dayjs.extend(utc);
dayjs.extend(timezone);

const dayjsLocales = import.meta.glob<unknown>(
  '../../../node_modules/dayjs/locale/*.js',
);

/**
 * Switch dayjs to the locale, falling back to English when it has none.
 */
export async function setDateLocale(locale: string): Promise<void> {
  const load = dayjsLocales[
    `../../../node_modules/dayjs/locale/${locale}.js`
  ] as (() => Promise<unknown>) | undefined;

  if (load) {
    await load();
  }

  dayjs.locale(load ? locale : 'en');
}

/**
 * Whether the browser knows the IANA zone, so formatting in it won't throw.
 */
function isValidTimezone(zone: string | null | undefined): zone is string {
  if (!zone) {
    return false;
  }

  try {
    new Intl.DateTimeFormat(undefined, { timeZone: zone });

    return true;
  } catch {
    return false;
  }
}

/**
 * Show dates in the user's saved timezone, falling back to the browser's when it's
 * missing, empty or unknown. Every helper below formats and parses in this zone.
 */
export function setDisplayTimezone(zone: string | null | undefined): void {
  dayjs.tz.setDefault(isValidTimezone(zone) ? zone : guessTimezone());
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
 * The span between two ISO dates, e.g. "45m" or "2h 10m".
 */
export function formatDuration(from: string, to: string): string {
  const minutes = Math.max(0, dayjs(to).diff(dayjs(from), 'minute'));
  const days = Math.floor(minutes / 1440);
  const hours = Math.floor((minutes % 1440) / 60);
  const parts = [
    days && `${days}d`,
    hours && `${hours}h`,
    (minutes % 60 || !minutes) && `${minutes % 60}m`,
  ];

  return parts.filter(Boolean).join(' ');
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

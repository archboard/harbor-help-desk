const relativeTime = new Intl.RelativeTimeFormat(undefined, {
  numeric: 'auto',
});

const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ['year', 31_536_000],
  ['month', 2_592_000],
  ['week', 604_800],
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
];

/**
 * A short, localized "3 hours ago" / "in 2 days" for an ISO date.
 */
export function timeAgo(iso: string | null): string {
  if (!iso) {
    return '—';
  }

  const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
  const [unit, size] = units.find(([, size]) => Math.abs(seconds) >= size) ?? [
    'second',
    1,
  ];

  return relativeTime.format(Math.round(seconds / size), unit);
}

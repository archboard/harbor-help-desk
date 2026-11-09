import dayjs from 'dayjs';
import { trans } from 'laravel-vue-i18n';
import type { EChartsOption } from 'echarts';

export type ValueKind = 'count' | 'minutes' | 'percent';

/**
 * Minutes as the two largest units, e.g. "45m", "2h 10m" or "3d 4h".
 */
export function formatMinutes(minutes: number): string {
  if (minutes < 60) {
    return `${Math.round(minutes)}m`;
  }

  if (minutes < 1440) {
    const rest = Math.round(minutes % 60);

    return `${Math.floor(minutes / 60)}h${rest ? ` ${rest}m` : ''}`;
  }

  const hours = Math.round((minutes % 1440) / 60);

  return `${Math.floor(minutes / 1440)}d${hours ? ` ${hours}h` : ''}`;
}

export function formatValue(
  value: number | null | undefined,
  kind: ValueKind,
): string {
  if (value === null || value === undefined) {
    return '—';
  }

  return {
    count: () => value.toLocaleString(),
    minutes: () => formatMinutes(value),
    percent: () => `${Number(value.toFixed(1))}%`,
  }[kind]();
}

/**
 * A bucket's label: its day, or the week it starts.
 */
export function formatBucket(date: string, bucket: string): string {
  return bucket === 'week'
    ? trans('Week of :date', { date: dayjs(date).format('MMM D') })
    : dayjs(date).format('MMM D');
}

/**
 * A trend line per named series, over every bucket of the period.
 */
export function lineChart(
  points: App.Data.Reports.SeriesPointData[],
  series: { key: string; label: string }[],
  kind: ValueKind,
  bucket: string,
): EChartsOption {
  return {
    grid: {
      left: 8,
      right: 16,
      top: series.length > 1 ? 40 : 16,
      bottom: 8,
      containLabel: true,
    },
    legend: { show: series.length > 1, top: 0, left: 0 },
    tooltip: {
      trigger: 'axis',
      valueFormatter: (value) => formatValue(value as number | null, kind),
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: points.map((point) => formatBucket(point.date, bucket)),
    },
    yAxis: {
      type: 'value',
      min: 0,
      axisLabel: { formatter: (value: number) => formatValue(value, kind) },
    },
    series: series.map((line) => ({
      type: 'line',
      name: line.label,
      connectNulls: true,
      data: points.map((point) => point.values[line.key] ?? null),
    })),
  };
}

/**
 * One bar per slice, in the given order, in the first series color.
 */
export function barChart(
  items: App.Data.Reports.BreakdownData[],
  horizontal = false,
): EChartsOption {
  const categories = {
    type: 'category' as const,
    data: items.map((item) => item.label),
  };
  const values = { type: 'value' as const, minInterval: 1 };

  return {
    grid: { left: 8, right: 16, top: 16, bottom: 8, containLabel: true },
    tooltip: { trigger: 'item' },
    xAxis: horizontal ? values : categories,
    yAxis: horizontal ? { ...categories, inverse: true } : values,
    series: [
      {
        type: 'bar',
        name: trans('Tickets'),
        data: items.map((item) => item.count),
        itemStyle: { borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0] },
      },
    ],
  };
}

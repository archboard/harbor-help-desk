/**
 * Chart colors for each appearance: a categorical order validated for colour-blind
 * separation, a one-hue sequential ramp, two status colors and the chart chrome.
 */
const tokens = {
  light: {
    surface: '#ffffff',
    ink: '#0b0b0b',
    secondary: '#52514e',
    muted: '#898781',
    grid: '#e1e0d9',
    axis: '#c3c2b7',
    border: 'rgba(11,11,11,0.10)',
    series: [
      '#2a78d6',
      '#eb6834',
      '#1baf7a',
      '#eda100',
      '#e87ba4',
      '#008300',
      '#6250d6',
      '#e34948',
    ],
    sequential: ['#cde2fb', '#86b6ef', '#3987e5', '#1c5cab', '#0d366b'],
  },
  dark: {
    surface: '#111827',
    ink: '#f0efec',
    secondary: '#c3c2b7',
    muted: '#898781',
    grid: '#2c2c2a',
    axis: '#383835',
    border: 'rgba(255,255,255,0.10)',
    series: [
      '#3987e5',
      '#d95926',
      '#199e70',
      '#c98500',
      '#d55181',
      '#008300',
      '#9085e9',
      '#e66767',
    ],
    sequential: ['#184f95', '#1c5cab', '#2a78d6', '#5598e7', '#9ec5f4'],
  },
};

export const statusColors = { good: '#0ca30c', critical: '#d03b3b' };

export type ChartTokens = (typeof tokens)['light'];

export function chartTokens(dark: boolean): ChartTokens {
  return dark ? tokens.dark : tokens.light;
}

/**
 * The ECharts theme for an appearance: thin marks, recessive axes and grid, and text in
 * ink colors rather than series colors.
 */
export function chartTheme(dark: boolean): Record<string, unknown> {
  const t = chartTokens(dark);

  return {
    color: t.series,
    backgroundColor: 'transparent',
    textStyle: { color: t.secondary, fontFamily: 'inherit' },
    legend: {
      textStyle: { color: t.secondary },
      icon: 'roundRect',
      itemWidth: 12,
      itemHeight: 12,
    },
    tooltip: {
      backgroundColor: t.surface,
      borderColor: t.border,
      textStyle: { color: t.ink },
    },
    categoryAxis: {
      axisLine: { lineStyle: { color: t.axis } },
      axisTick: { show: false },
      axisLabel: { color: t.muted },
      splitLine: { show: false },
    },
    valueAxis: {
      axisLine: { show: false },
      axisLabel: { color: t.muted },
      splitLine: { lineStyle: { color: t.grid } },
    },
    line: { lineStyle: { width: 2 }, symbolSize: 8, showSymbol: false },
    bar: { itemStyle: { borderRadius: [4, 4, 0, 0] }, barMaxWidth: 24 },
  };
}

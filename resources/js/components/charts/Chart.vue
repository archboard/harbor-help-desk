<script setup lang="ts">
import type { EChartsOption } from 'echarts';
import { BarChart, GaugeChart, HeatmapChart, LineChart } from 'echarts/charts';
import {
  DataZoomComponent,
  GridComponent,
  LegendComponent,
  TooltipComponent,
  VisualMapComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useIsDark } from '@/composables/useIsDark';
import { chartTheme } from '@/lib/charts';

// Only what the reports draw, so the rest of ECharts stays out of the bundle.
use([
  BarChart,
  CanvasRenderer,
  DataZoomComponent,
  GaugeChart,
  GridComponent,
  HeatmapChart,
  LegendComponent,
  LineChart,
  TooltipComponent,
  VisualMapComponent,
]);

const {
  option,
  label,
  height = '16rem',
} = defineProps<{
  option: EChartsOption;
  /** What the chart shows, for screen readers. */
  label: string;
  height?: string;
}>();

const isDark = useIsDark();
const theme = computed(() => chartTheme(isDark.value));
</script>

<template>
  <VChart
    :key="isDark ? 'dark' : 'light'"
    :option="option"
    :theme="theme"
    :style="{ height }"
    autoresize
    role="img"
    :aria-label="label"
  />
</template>

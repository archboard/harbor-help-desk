<script setup lang="ts">
import type { EChartsOption } from 'echarts';
import { computed } from 'vue';
import Chart from '@/components/charts/Chart.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { useIsDark } from '@/composables/useIsDark';
import { chartTokens, statusColors } from '@/lib/charts';
import { formatValue, lineChart } from '@/lib/reports';

const { data, metrics, priorities } = defineProps<{
  data: App.Data.Reports.SlaReportData;
  metrics: Record<App.Enums.SlaMetric, string>;
  priorities: Record<App.Enums.TicketPriority, string>;
}>();

const isDark = useIsDark();

function gauge(percent: number | null): EChartsOption {
  const tokens = chartTokens(isDark.value);

  return {
    series: [
      {
        type: 'gauge',
        startAngle: 210,
        endAngle: -30,
        min: 0,
        max: 100,
        radius: '100%',
        progress: {
          show: true,
          width: 12,
          roundCap: true,
          itemStyle: { color: tokens.series[0] },
        },
        axisLine: { lineStyle: { width: 12, color: [[1, tokens.grid]] } },
        pointer: { show: false },
        axisTick: { show: false },
        splitLine: { show: false },
        axisLabel: { show: false },
        detail: {
          offsetCenter: [0, 0],
          fontSize: 24,
          fontWeight: 600,
          color: tokens.ink,
          formatter: () => formatValue(percent, 'percent'),
        },
        data: [{ value: percent ?? 0 }],
      },
    ],
  };
}

const breaches = computed(() =>
  lineChart(
    data.breaches,
    data.metrics.map((metric) => ({
      key: metric.metric,
      label: metrics[metric.metric],
    })),
    'count',
    data.period.bucket,
  ),
);
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
      <ReportCard
        v-for="metric in data.metrics"
        :key="metric.metric"
        :title="metrics[metric.metric]"
      >
        <div class="grid items-center gap-4 sm:grid-cols-2">
          <Chart
            :label="`${metrics[metric.metric]} targets met`"
            :option="gauge(metric.percent_met.value)"
            height="10rem"
          />
          <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
              <dt class="flex items-center gap-2">
                <span
                  class="size-2.5 rounded-full"
                  :style="{ backgroundColor: statusColors.good }"
                  aria-hidden="true"
                />
                Met
              </dt>
              <dd class="font-medium tabular-nums">{{ metric.met }}</dd>
            </div>
            <div class="flex items-center justify-between gap-2">
              <dt class="flex items-center gap-2">
                <span
                  class="size-2.5 rounded-full"
                  :style="{ backgroundColor: statusColors.critical }"
                  aria-hidden="true"
                />
                Breached
              </dt>
              <dd class="font-medium tabular-nums">{{ metric.breached }}</dd>
            </div>
            <div class="flex items-center justify-between gap-2">
              <dt class="flex items-center gap-2">
                <span
                  class="size-2.5 rounded-full bg-gray-300 dark:bg-gray-600"
                  aria-hidden="true"
                />
                In progress
              </dt>
              <dd class="font-medium tabular-nums">
                {{ metric.in_progress }}
              </dd>
            </div>
          </dl>
        </div>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Share met of targets met or breached;
          {{ formatValue(metric.percent_met.previous, 'percent') }} in the
          previous period.
        </p>

        <div class="mt-4">
          <Table hide-shadow>
            <Thead>
              <tr>
                <Th>Priority</Th>
                <Th right>Met</Th>
                <Th right>Breached</Th>
                <Th right>% met</Th>
              </tr>
            </Thead>
            <Tbody>
              <tr v-for="row in metric.by_priority" :key="row.priority">
                <Td darker>{{ priorities[row.priority] }}</Td>
                <Td class="text-end tabular-nums">{{ row.met }}</Td>
                <Td class="text-end tabular-nums">{{ row.breached }}</Td>
                <Td class="text-end tabular-nums">{{
                  formatValue(row.percent_met, 'percent')
                }}</Td>
              </tr>
            </Tbody>
          </Table>
        </div>
      </ReportCard>
    </div>

    <ReportCard title="Breaches">
      <Chart label="SLA breaches over time" :option="breaches" />
    </ReportCard>
  </div>
</template>

<script setup lang="ts">
import type { EChartsOption } from 'echarts';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import Chart from '@/components/charts/Chart.vue';
import KpiTile from '@/components/reports/KpiTile.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import { useIsDark } from '@/composables/useIsDark';
import { chartTokens } from '@/lib/charts';
import { barChart, lineChart } from '@/lib/reports';

const { data } = defineProps<{ data: App.Data.Reports.OverviewReportData }>();

const isDark = useIsDark();

const heatmap = computed<EChartsOption>(() => {
  const tokens = chartTokens(isDark.value);
  const weekdays = [
    trans('Mon'),
    trans('Tue'),
    trans('Wed'),
    trans('Thu'),
    trans('Fri'),
    trans('Sat'),
    trans('Sun'),
  ];

  return {
    grid: { left: 8, right: 8, top: 8, bottom: 8, containLabel: true },
    tooltip: {
      trigger: 'item',
      formatter: (params) => {
        const [hour, day, count] = (params as { value: number[] }).value;

        return `${weekdays[day]} ${String(hour).padStart(2, '0')}:00 · ${transChoice(
          '{1} :count ticket|[0,*] :count tickets',
          count,
          { count: String(count) },
        )}`;
      },
    },
    xAxis: {
      type: 'category',
      data: Array.from({ length: 24 }, (_, hour) => String(hour)),
    },
    yAxis: { type: 'category', data: weekdays, inverse: true },
    visualMap: {
      show: false,
      min: 0,
      max: Math.max(1, ...data.heatmap.map((cell) => cell.count)),
      inRange: { color: tokens.sequential },
    },
    series: [
      {
        type: 'heatmap',
        data: data.heatmap.map((cell) => [
          cell.hour,
          cell.weekday - 1,
          cell.count,
        ]),
        itemStyle: {
          borderColor: tokens.surface,
          borderWidth: 2,
          borderRadius: 4,
        },
      },
    ],
  };
});
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
      <KpiTile
        :label="$t('Created')"
        :kpi="data.created"
        kind="count"
        goodWhen="neutral"
      />
      <KpiTile :label="$t('Resolved')" :kpi="data.resolved" kind="count" />
      <KpiTile
        :label="$t('Reopened')"
        :kpi="data.reopened"
        kind="count"
        goodWhen="down"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <ReportCard :title="$t('Created vs resolved')" class="lg:col-span-2">
        <Chart
          :label="$t('Tickets created and resolved over time')"
          :option="
            lineChart(
              data.volume,
              [
                { key: 'created', label: $t('Created') },
                { key: 'resolved', label: $t('Resolved') },
              ],
              'count',
              data.period.bucket,
            )
          "
        />
      </ReportCard>

      <ReportCard :title="$t('Open now')">
        <dl class="divide-y divide-gray-100 dark:divide-gray-800">
          <div
            v-for="status in data.backlog"
            :key="status.key"
            class="flex justify-between py-2 text-sm"
          >
            <dt class="text-gray-600 dark:text-gray-400">
              {{ status.label }}
            </dt>
            <dd class="font-medium tabular-nums">{{ status.count }}</dd>
          </div>
        </dl>
      </ReportCard>
    </div>

    <ReportCard :title="$t('When tickets arrive')">
      <Chart
        :label="$t('Tickets created by weekday and hour')"
        :option="heatmap"
        height="14rem"
      />
    </ReportCard>

    <div class="grid gap-6 lg:grid-cols-2">
      <ReportCard :title="$t('By source')">
        <Chart
          :label="$t('Tickets created by source')"
          :option="barChart(data.by_source, true)"
          height="12rem"
        />
      </ReportCard>
      <ReportCard :title="$t('By desk')">
        <Chart
          :label="$t('Tickets created by desk')"
          :option="barChart(data.by_desk, true)"
          height="12rem"
        />
      </ReportCard>
    </div>
  </div>
</template>

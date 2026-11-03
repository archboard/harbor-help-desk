<script setup lang="ts">
import Chart from '@/components/charts/Chart.vue';
import KpiTile from '@/components/reports/KpiTile.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import { barChart, lineChart } from '@/lib/reports';

const { data } = defineProps<{ data: App.Data.Reports.ResolutionReportData }>();
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
      <KpiTile
        label="Median resolution"
        :kpi="data.median_resolution"
        kind="minutes"
        goodWhen="down"
      />
      <KpiTile
        label="Average resolution"
        :kpi="data.average_resolution"
        kind="minutes"
        goodWhen="down"
      />
      <KpiTile
        label="Reopened after resolving"
        :kpi="data.reopen_rate"
        kind="percent"
        goodWhen="down"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <ReportCard title="Median resolution time" class="lg:col-span-2">
        <Chart
          label="Median resolution time over time"
          :option="
            lineChart(
              data.trend,
              [{ key: 'resolution', label: 'Resolution' }],
              'minutes',
              data.period.bucket,
            )
          "
        />
      </ReportCard>
      <ReportCard title="Resolution times">
        <Chart
          label="Tickets by resolution time"
          :option="barChart(data.distribution)"
        />
      </ReportCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import Chart from '@/components/charts/Chart.vue';
import KpiTile from '@/components/reports/KpiTile.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import { barChart, lineChart } from '@/lib/reports';

const { data } = defineProps<{ data: App.Data.Reports.ResponsesReportData }>();
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <KpiTile
        label="Median first response"
        :kpi="data.median_first_response"
        kind="minutes"
        goodWhen="down"
      />
      <KpiTile
        label="Average first response"
        :kpi="data.average_first_response"
        kind="minutes"
        goodWhen="down"
      />
      <KpiTile
        label="Median reply time"
        :kpi="data.median_reply"
        kind="minutes"
        goodWhen="down"
      />
      <KpiTile
        label="Average reply time"
        :kpi="data.average_reply"
        kind="minutes"
        goodWhen="down"
      />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <KpiTile
        label="First response within 1 hour"
        :kpi="data.first_response_within_hour"
        kind="percent"
      />
      <KpiTile
        label="First response within 4 hours"
        :kpi="data.first_response_within_four_hours"
        kind="percent"
      />
      <KpiTile
        label="First response within 24 hours"
        :kpi="data.first_response_within_day"
        kind="percent"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <ReportCard title="Median response times" class="lg:col-span-2">
        <Chart
          label="Median first response and reply time over time"
          :option="
            lineChart(
              data.trend,
              [
                { key: 'first_response', label: 'First response' },
                { key: 'reply', label: 'Reply' },
              ],
              'minutes',
              data.period.bucket,
            )
          "
        />
      </ReportCard>
      <ReportCard title="First response times">
        <Chart
          label="Tickets by first response time"
          :option="barChart(data.first_response_distribution)"
        />
      </ReportCard>
    </div>
  </div>
</template>

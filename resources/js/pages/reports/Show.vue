<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: { title: 'Reports' } });

// ponytail: placeholder until the reports UI step draws the charts.
defineProps<{
  report: App.Enums.Report;
  reports: Record<App.Enums.Report, string>;
  data:
    | App.Data.Reports.OverviewReportData
    | App.Data.Reports.ResponsesReportData
    | App.Data.Reports.ResolutionReportData
    | App.Data.Reports.AgentsReportData
    | App.Data.Reports.SlaReportData
    | App.Data.Reports.SatisfactionReportData;
}>();
</script>

<template>
  <div>
    <Head :title="reports[report]" />
    <h2 class="text-lg font-semibold">{{ reports[report] }}</h2>
  </div>
</template>

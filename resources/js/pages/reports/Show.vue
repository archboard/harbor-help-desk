<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import AgentsReport from '@/components/reports/AgentsReport.vue';
import OverviewReport from '@/components/reports/OverviewReport.vue';
import ReportFilters from '@/components/reports/ReportFilters.vue';
import type { ReportQuery } from '@/components/reports/ReportFilters.vue';
import ResolutionReport from '@/components/reports/ResolutionReport.vue';
import ResponsesReport from '@/components/reports/ResponsesReport.vue';
import SatisfactionReport from '@/components/reports/SatisfactionReport.vue';
import SlaReport from '@/components/reports/SlaReport.vue';
import { show } from '@/routes/reports';

defineOptions({ layout: { title: trans('Reports') } });

type ReportData =
  | App.Data.Reports.OverviewReportData
  | App.Data.Reports.ResponsesReportData
  | App.Data.Reports.ResolutionReportData
  | App.Data.Reports.AgentsReportData
  | App.Data.Reports.SlaReportData
  | App.Data.Reports.SatisfactionReportData;

const { report, data } = defineProps<{
  report: App.Enums.Report;
  reports: Record<App.Enums.Report, string>;
  data: ReportData;
  desks: App.Data.Desks.DeskData[];
  bases: Record<App.Enums.ReportBasis, string>;
  slaMetrics: Record<App.Enums.SlaMetric, string>;
  priorities: Record<App.Enums.TicketPriority, string>;
}>();

const loading = ref(false);

const currentQuery = (): ReportQuery => ({
  from: data.period.from,
  to: data.period.to,
  desk_ids: data.period.desk_ids,
  basis: data.period.basis,
});

function load(query: ReportQuery): void {
  router.get(show(report).url, query, {
    preserveState: true,
    preserveScroll: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
  });
}
</script>

<template>
  <div class="space-y-6">
    <Head :title="$t(':report report', { report: reports[report] })" />

    <nav
      class="flex gap-6 overflow-x-auto border-b border-gray-200 dark:border-gray-800"
      :aria-label="$t('Reports')"
    >
      <Link
        v-for="(label, key) in reports"
        :key="key"
        :href="show(key, { query: currentQuery() })"
        preserve-scroll
        class="-mb-px border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap"
        :class="
          key === report
            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
        "
        :aria-current="key === report ? 'page' : undefined"
        @start="loading = true"
        @finish="loading = false"
      >
        {{ label }}
      </Link>
    </nav>

    <ReportFilters
      :period="data.period"
      :desks="desks"
      :bases="bases"
      @change="load"
    />

    <div
      :class="{ 'pointer-events-none animate-pulse opacity-60': loading }"
      :aria-busy="loading"
    >
      <OverviewReport
        v-if="report === 'overview'"
        :data="data as App.Data.Reports.OverviewReportData"
      />
      <ResponsesReport
        v-else-if="report === 'responses'"
        :data="data as App.Data.Reports.ResponsesReportData"
      />
      <ResolutionReport
        v-else-if="report === 'resolution'"
        :data="data as App.Data.Reports.ResolutionReportData"
      />
      <AgentsReport
        v-else-if="report === 'agents'"
        :data="data as App.Data.Reports.AgentsReportData"
      />
      <SlaReport
        v-else-if="report === 'sla'"
        :data="data as App.Data.Reports.SlaReportData"
        :metrics="slaMetrics"
        :priorities="priorities"
      />
      <SatisfactionReport
        v-else
        :data="data as App.Data.Reports.SatisfactionReportData"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Chart from '@/components/charts/Chart.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import KpiTile from '@/components/reports/KpiTile.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatValue, lineChart } from '@/lib/reports';
import { show as showTicket } from '@/routes/tickets';

const { data } = defineProps<{
  data: App.Data.Reports.SatisfactionReportData;
}>();
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <KpiTile label="Score" :kpi="data.score" kind="percent" />
      <KpiTile label="Rated good" :kpi="data.good" kind="count" />
      <KpiTile label="Rated bad" :kpi="data.bad" kind="count" goodWhen="down" />
      <KpiTile
        label="Surveys answered"
        :kpi="data.response_rate"
        kind="percent"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <ReportCard title="Score over time" class="lg:col-span-2">
        <Chart
          label="Satisfaction score over time"
          :option="
            lineChart(
              data.trend,
              [{ key: 'score', label: 'Score' }],
              'percent',
              data.period.bucket,
            )
          "
        />
      </ReportCard>

      <ReportCard title="By desk">
        <Table hide-shadow>
          <Thead>
            <tr>
              <Th>Desk</Th>
              <Th right>Good</Th>
              <Th right>Bad</Th>
              <Th right>Score</Th>
            </tr>
          </Thead>
          <Tbody>
            <tr v-for="desk in data.by_desk" :key="desk.desk_id">
              <Td darker>{{ desk.name }}</Td>
              <Td class="text-end tabular-nums">{{ desk.good }}</Td>
              <Td class="text-end tabular-nums">{{ desk.bad }}</Td>
              <Td class="text-end tabular-nums">{{
                formatValue(desk.score, 'percent')
              }}</Td>
            </tr>
            <tr v-if="data.by_desk.length === 0">
              <Td :colspan="4">No ratings yet.</Td>
            </tr>
          </Tbody>
        </Table>
      </ReportCard>
    </div>

    <ReportCard title="Latest comments">
      <ul
        v-if="data.comments.length"
        class="divide-y divide-gray-100 dark:divide-gray-800"
      >
        <li
          v-for="comment in data.comments"
          :key="comment.ticket_id"
          class="flex gap-4 py-3"
        >
          <Pill :color="comment.rating === 'good' ? 'green' : 'red'">
            {{ comment.rating === 'good' ? 'Good' : 'Bad' }}
          </Pill>
          <div class="min-w-0 flex-1">
            <p class="text-sm whitespace-pre-line">{{ comment.comment }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              <Link
                :href="showTicket(comment.ticket_id)"
                class="font-medium hover:underline"
                >#{{ comment.ticket_number }}</Link
              >
              ·
              <RelativeTime :datetime="comment.created_at" />
            </p>
          </div>
        </li>
      </ul>
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        No comments in this period.
      </p>
    </ReportCard>
  </div>
</template>

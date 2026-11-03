<script setup lang="ts">
import { Head, InfiniteScroll } from '@inertiajs/vue3';
import CardWrapper from '@/components/CardWrapper.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { show as showContact } from '@/routes/contacts';

defineOptions({ layout: { title: 'Data & privacy' } });

defineProps<{
  exports: { data: App.Data.Exports.ExportData[] };
}>();

const typeLabels: Record<App.Enums.ExportType, string> = {
  contact: 'Contact data',
  workspace: 'Workspace',
};

const statusLabels: Record<App.Enums.ExportStatus, string> = {
  pending: 'Preparing',
  completed: 'Ready',
  failed: 'Failed',
};

function formatSize(bytes: number | null): string {
  if (bytes === null) {
    return '';
  }

  return bytes < 1024 * 1024
    ? `${Math.max(1, Math.round(bytes / 1024))} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
  <div class="space-y-6">
    <Head title="Data & privacy" />

    <p class="text-sm text-gray-500 dark:text-gray-400">
      Exports are requested from a contact's page. They expire after seven days.
    </p>

    <CardWrapper>
      <p
        v-if="exports.data.length === 0"
        class="p-6 text-sm text-gray-500 dark:text-gray-400"
      >
        No exports yet.
      </p>

      <InfiniteScroll v-else data="exports" as="ul" class="divide-y">
        <li
          v-for="item in exports.data"
          :key="item.id"
          class="flex items-start justify-between gap-4 px-6 py-3 text-sm"
        >
          <div class="min-w-0">
            <p class="text-gray-900 dark:text-gray-100">
              <span class="font-medium">{{ typeLabels[item.type] }}</span>
              <template v-if="item.subject_id">
                &middot;
                <a
                  v-if="item.subject_name"
                  :href="showContact(item.subject_id).url"
                  class="text-primary-600 dark:text-primary-400"
                >
                  {{ item.subject_name }}
                </a>
                <span v-else class="text-gray-500">Deleted contact</span>
              </template>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ statusLabels[item.status] }}
              <template v-if="item.size !== null">
                &middot; {{ formatSize(item.size) }}
              </template>
              <template v-if="item.requested_by_name">
                &middot; requested by {{ item.requested_by_name }}
              </template>
              &middot; expires
              <RelativeTime :datetime="item.expires_at" />
            </p>
          </div>
          <a
            v-if="item.download_url"
            :href="item.download_url"
            class="shrink-0 font-medium text-primary-600 dark:text-primary-400"
          >
            Download
          </a>
        </li>
      </InfiniteScroll>
    </CardWrapper>
  </div>
</template>

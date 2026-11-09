<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import {
  Head,
  InfiniteScroll,
  router,
  useForm,
  usePage,
} from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { NumberField } from '@/components/forms/fields';
import RelativeTime from '@/components/RelativeTime.vue';
import { show as showContact } from '@/routes/contacts';
import { store as importWorkspace } from '@/routes/workspace/import';
import { store as requestWorkspaceExport } from '@/routes/workspace/exports';
import { update as updateSettings } from '@/routes/workspace/settings';

defineOptions({ layout: { title: trans('Data & privacy') } });

defineProps<{
  exports: { data: App.Data.Exports.ExportData[] };
}>();

const settings = usePage().props.workspace?.settings;

const form = useForm({
  retention_resolved_ticket_days:
    settings?.retention_resolved_ticket_days ?? null,
  retention_trash_days: settings?.retention_trash_days ?? 30,
});

const importForm = useForm<{ file: File | null }>({ file: null });

function submitImport(): void {
  importForm.post(importWorkspace.url(), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => importForm.reset(),
  });
}

const typeLabels = computed<Record<App.Enums.ExportType, string>>(() => ({
  contact: trans('Contact data'),
  workspace: trans('Workspace'),
}));

const statusLabels = computed<Record<App.Enums.ExportStatus, string>>(() => ({
  pending: trans('Preparing'),
  completed: trans('Ready'),
  failed: trans('Failed'),
}));

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
    <Head :title="$t('Data & privacy')" />

    <CardWrapper>
      <form
        @submit.prevent="
          form.submit(updateSettings(), { preserveScroll: true })
        "
      >
        <CardPadding>
          <AppFieldset>
            <NumberField
              v-model="form.retention_resolved_ticket_days"
              :label="$t('Delete resolved tickets after (days)')"
              :help-text="
                $t(
                  'Solved and closed tickets move to the trash this many days after they were resolved. Leave empty to keep them forever.',
                )
              "
              :error="form.errors.retention_resolved_ticket_days"
              :min="1"
              :max="3650"
            />
            <NumberField
              v-model="form.retention_trash_days"
              :label="$t('Keep deleted tickets for (days)')"
              :help-text="
                $t(
                  'After this many days in the trash, a ticket and its files are removed permanently.',
                )
              "
              :error="form.errors.retention_trash_days"
              :min="1"
              :max="365"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="form.processing" />
      </form>
    </CardWrapper>

    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            "A contact's data is exported from their page. Export the whole workspace here, with its files but without mailbox passwords or tokens. Exports expire after seven days.",
          )
        }}
      </p>
      <AppButton
        type="button"
        class="shrink-0"
        @click="
          router.post(requestWorkspaceExport(), {}, { preserveScroll: true })
        "
        >{{ $t('Export workspace') }}</AppButton
      >
    </div>

    <CardWrapper>
      <p
        v-if="exports.data.length === 0"
        class="p-6 text-sm text-gray-500 dark:text-gray-400"
      >
        {{ $t('No exports yet.') }}
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
                <span v-else class="text-gray-500">{{
                  $t('Deleted contact')
                }}</span>
              </template>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ statusLabels[item.status] }}
              <template v-if="item.size !== null">
                &middot; {{ formatSize(item.size) }}
              </template>
              <template v-if="item.requested_by_name">
                &middot;
                {{ $t('requested by :name', { name: item.requested_by_name }) }}
              </template>
              &middot; {{ $t('expires') }}
              <RelativeTime :datetime="item.expires_at" />
            </p>
          </div>
          <a
            v-if="item.download_url"
            :href="item.download_url"
            class="shrink-0 font-medium text-primary-600 dark:text-primary-400"
            >{{ $t('Download') }}</a
          >
        </li>
      </InfiniteScroll>
    </CardWrapper>

    <CardWrapper>
      <form @submit.prevent="submitImport">
        <CardPadding>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{
              $t(
                'Import a workspace export (a .zip from "Export workspace") into this workspace. It only works while the workspace has no tickets or contacts. Mailboxes come in disconnected and automation webhook URLs must be generated again. Members are matched by email; new ones sign in with a magic link.',
              )
            }}
          </p>
          <input
            type="file"
            accept=".zip,application/zip"
            class="mt-3 block text-sm"
            @input="
              importForm.file =
                ($event.target as HTMLInputElement).files?.[0] ?? null
            "
          />
          <p v-if="importForm.errors.file" class="mt-1 text-sm text-red-600">
            {{ importForm.errors.file }}
          </p>
        </CardPadding>
        <CardAction>
          <AppButton
            type="submit"
            :loading="importForm.processing"
            :disabled="!importForm.file"
            >{{ $t('Import workspace') }}</AppButton
          >
        </CardAction>
      </form>
    </CardWrapper>
  </div>
</template>

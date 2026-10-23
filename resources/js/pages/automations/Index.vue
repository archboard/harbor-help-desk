<script setup lang="ts">
import { Switch } from '@headlessui/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Workflow } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import {
  create,
  destroy,
  duplicate,
  edit,
  reorder,
} from '@/routes/automations';
import { update as setEnabled } from '@/routes/automations/enabled';

defineOptions({ layout: { title: 'Automations' } });

type Automation = App.Data.Automations.AutomationData;

const { automations, desks, triggers, statuses } = defineProps<{
  automations: Automation[];
  desks: App.Data.Desks.DeskData[];
  triggers: Record<string, string>;
  statuses: Record<App.Enums.AutomationRunStatus, string>;
}>();

const statusColors: Record<
  App.Enums.AutomationRunStatus,
  'blue' | 'yellow' | 'green' | 'gray' | 'red'
> = {
  running: 'blue',
  waiting: 'yellow',
  completed: 'green',
  skipped: 'gray',
  failed: 'red',
  cancelled: 'gray',
};

const deleting = ref<Automation | null>(null);
const deleteLoading = ref(false);

const deskNames = computed(
  () => new Map(desks.map((desk) => [desk.id, desk.name])),
);

/** Trigger types that have automations, in the order the registry lists them, then any unknown ones. */
const groups = computed(() => {
  const types = [
    ...Object.keys(triggers),
    ...new Set(automations.map((automation) => automation.trigger_type)),
  ];

  return [...new Set(types)]
    .map((type) => ({
      type,
      label: triggers[type] ?? type,
      automations: automations.filter(
        (automation) => automation.trigger_type === type,
      ),
    }))
    .filter((group) => group.automations.length);
});

function toggle(automation: Automation, enabled: boolean): void {
  router
    .optimistic((props: { automations: Automation[] }) => ({
      automations: props.automations.map((item) =>
        item.id === automation.id ? { ...item, enabled } : item,
      ),
    }))
    .put(setEnabled(automation.id).url, { enabled }, { preserveScroll: true });
}

/** Swap with the neighbour in the same trigger type. */
function move(group: Automation[], index: number, offset: -1 | 1): void {
  const ids = group.map((automation) => automation.id);

  [ids[index], ids[index + offset]] = [ids[index + offset], ids[index]];

  router.post(reorder().url, { ids }, { preserveScroll: true });
}

function copy(automation: Automation): void {
  router.post(duplicate(automation.id).url, {}, { preserveScroll: true });
}

function remove(close: () => void): void {
  router.delete(destroy(deleting.value!.id).url, {
    preserveScroll: true,
    onStart: () => (deleteLoading.value = true),
    onFinish: () => (deleteLoading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <Head title="Automations" />

  <div class="space-y-8">
    <div class="flex items-start justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Automations run their steps when something happens to a ticket, in the
        order shown for each trigger.
      </p>
      <AppButton :component="Link" :href="create().url" class="shrink-0">
        New automation
      </AppButton>
    </div>

    <section v-for="group in groups" :key="group.type" class="space-y-3">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">
        {{ group.label }}
      </h2>

      <Table>
        <Thead>
          <tr>
            <Th>Name</Th>
            <Th>Scope</Th>
            <Th>Enabled</Th>
            <Th>Last run</Th>
            <Th>Runs (24h)</Th>
            <Th right><span class="sr-only">Actions</span></Th>
          </tr>
        </Thead>
        <Tbody>
          <tr
            v-for="(automation, index) in group.automations"
            :key="automation.id"
          >
            <Td darker>
              <Link
                :href="edit(automation.id).url"
                class="hover:text-primary-600 hover:underline"
              >
                {{ automation.name }}
              </Link>
            </Td>
            <Td>
              <Pill :color="automation.desk_id ? 'blue' : 'gray'">
                {{
                  automation.desk_id
                    ? (deskNames.get(automation.desk_id) ?? 'Desk')
                    : 'Workspace'
                }}
              </Pill>
            </Td>
            <Td>
              <Switch
                :model-value="automation.enabled"
                :class="[
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:outline-hidden',
                  automation.enabled
                    ? 'bg-primary-600'
                    : 'bg-gray-200 dark:bg-gray-700',
                ]"
                @update:model-value="toggle(automation, $event)"
              >
                <span class="sr-only">Enabled</span>
                <span
                  :class="[
                    'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm transition-transform',
                    automation.enabled ? 'translate-x-5' : 'translate-x-0',
                  ]"
                />
              </Switch>
            </Td>
            <Td>
              <template v-if="automation.last_run">
                <Pill :color="statusColors[automation.last_run.status]">
                  {{ statuses[automation.last_run.status] }}
                </Pill>
                <RelativeTime
                  :datetime="automation.last_run.created_at"
                  class="ms-2 text-xs text-gray-500"
                />
              </template>
              <span v-else class="text-gray-500">Never</span>
            </Td>
            <Td>{{ automation.runs_last24h ?? 0 }}</Td>
            <Td class="space-x-2 text-end whitespace-nowrap">
              <AppButton
                type="button"
                size="xs"
                color="white"
                :disabled="index === 0"
                @click="move(group.automations, index, -1)"
              >
                <ArrowUp class="size-3" aria-hidden="true" />
                <span class="sr-only">Move up</span>
              </AppButton>
              <AppButton
                type="button"
                size="xs"
                color="white"
                :disabled="index === group.automations.length - 1"
                @click="move(group.automations, index, 1)"
              >
                <ArrowDown class="size-3" aria-hidden="true" />
                <span class="sr-only">Move down</span>
              </AppButton>
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="copy(automation)"
              >
                Duplicate
              </AppButton>
              <AppButton
                type="button"
                size="xs"
                color="red"
                @click="deleting = automation"
              >
                Delete
              </AppButton>
            </Td>
          </tr>
        </Tbody>
      </Table>
    </section>

    <div
      v-if="!groups.length"
      class="rounded-xl border-2 border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700"
    >
      <Workflow
        class="mx-auto size-12 text-gray-400 dark:text-gray-500"
        aria-hidden="true"
      />
      <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
        No automations yet
      </h2>
      <p class="mt-2 text-base text-gray-500 dark:text-gray-400">
        Automations set fields, reply, assign and notify for you when tickets
        arrive, change or sit idle.
      </p>
      <AppButton :component="Link" :href="create().url" class="mt-6">
        New automation
      </AppButton>
    </div>
  </div>

  <ConfirmationModal
    v-if="deleting"
    :loading="deleteLoading"
    @close="deleting = null"
    @confirmed="remove"
  >
    <template #headline>Delete “{{ deleting.name }}”?</template>
    It will stop running, and its run history is deleted with it.
    <template #actionText>Delete automation</template>
  </ConfirmationModal>
</template>

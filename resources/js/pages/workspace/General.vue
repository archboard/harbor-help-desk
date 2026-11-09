<script setup lang="ts">
import { Deferred, Head, useForm, usePage } from '@inertiajs/vue3';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import Pill from '@/components/Pill.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { InputField } from '@/components/forms/fields';
import { update } from '@/routes/workspace/general';

defineOptions({ layout: { title: 'Workspace' } });

defineProps<{
  /** Deferred, self-hosted only. */
  status?: App.Data.Install.SystemStatusData;
}>();

const page = usePage();

const form = useForm({
  name: page.props.workspace?.name ?? '',
} satisfies App.Data.Workspaces.UpdateWorkspaceData);
</script>

<template>
  <Head title="Workspace" />

  <CardWrapper>
    <form @submit.prevent="form.submit(update(), { preserveScroll: true })">
      <CardPadding>
        <AppFieldset>
          <InputField
            v-model="form.name"
            label="Name"
            :error="form.errors.name"
            required
          />
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>

  <CardWrapper v-if="status || $page.props.multitenant === false">
    <CardPadding>
      <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
        System status
      </h3>
      <Deferred data="status">
        <template #fallback>
          <div class="animate-pulse space-y-2" aria-label="Checking services">
            <div class="h-5 w-2/3 rounded bg-gray-200 dark:bg-gray-800" />
            <div class="h-5 w-1/2 rounded bg-gray-200 dark:bg-gray-800" />
            <div class="h-5 w-3/5 rounded bg-gray-200 dark:bg-gray-800" />
          </div>
        </template>

        <ul v-if="status" class="space-y-2 text-sm">
          <li
            v-for="check in status.checks"
            :key="check.name"
            class="flex gap-3"
          >
            <Pill
              :color="check.ok ? 'green' : 'red'"
              class="shrink-0 self-start"
            >
              {{ check.name }}
            </Pill>
            <span class="text-gray-600 dark:text-gray-300">{{
              check.detail
            }}</span>
          </li>
          <li class="text-gray-500 dark:text-gray-400">
            Harbor Help {{ status.version }}
          </li>
        </ul>
      </Deferred>
    </CardPadding>
  </CardWrapper>
</template>

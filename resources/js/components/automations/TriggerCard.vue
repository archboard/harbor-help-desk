<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, inject } from 'vue';
import type { OptionsInput } from '@/types';
import { builderKey } from './builder';
import ConfigForm from './ConfigForm.vue';
import { defaultConfig } from './configRules';
import StepCard from './StepCard.vue';
import { configSummary, summarizeTrigger } from './summaries';
import type { TestResult } from './testResult';
import { triggerIcon } from './triggerIcons';
import WebhookUrl from './WebhookUrl.vue';

const {
  catalog,
  desks,
  errors = {},
  invalid = false,
  messages = [],
  automation = null,
  testResult = null,
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  desks: App.Data.Desks.DeskData[];
  /** Server errors keyed by config key. */
  errors?: Record<string, string>;
  invalid?: boolean;
  messages?: string[];
  /** The saved automation, for the webhook URL. */
  automation?: App.Data.Automations.AutomationData | null;
  /** The latest test run, if it is still current. */
  testResult?: TestResult | null;
}>();

const trigger = defineModel<App.Data.Automations.TriggerData>({
  required: true,
});
const open = defineModel<boolean>('open', { default: false });

const page = usePage();

const readonly = inject(builderKey)?.readonly ?? false;

const current = computed(() =>
  catalog.triggers.find((item) => item.key === trigger.value.type),
);

const deskNames = computed(() =>
  Object.fromEntries(desks.map((desk) => [desk.id, desk.name])),
);

const choices = computed<Record<string, OptionsInput>>(() => ({
  desk_id: desks.map((desk) => ({ value: desk.id, label: desk.name })),
  timezone: Intl.supportedValuesOf('timeZone'),
}));

function pick(key: string): void {
  if (key === trigger.value.type) {
    return;
  }

  const { config_rules: rules, config_options: options } =
    catalog.triggers.find((item) => item.key === key)!;

  trigger.value = {
    type: key,
    config: {
      ...defaultConfig(rules, options),
      ...(key === 'schedule'
        ? {
            time: '09:00',
            timezone: page.props.auth.user.timezone ?? 'UTC',
          }
        : {}),
    },
  };
}
</script>

<template>
  <StepCard
    v-model:open="open"
    :icon="triggerIcon(trigger.type)"
    :kind="$t('Trigger')"
    :summary="summarizeTrigger(trigger, catalog)"
    :details="
      current
        ? configSummary(
            trigger.config,
            current.config_rules,
            current.config_options,
            { desk_id: deskNames },
          )
        : undefined
    "
    :invalid="invalid"
    :errors="messages"
    :status="
      testResult
        ? testResult.trigger_matched === null
          ? 'skip'
          : testResult.trigger_matched
            ? 'pass'
            : 'fail'
        : undefined
    "
    :note="
      testResult
        ? (testResult.trigger_note ??
          (testResult.trigger_matched
            ? $t('The trigger matches the chosen event.')
            : $t('The trigger does not match the chosen event.')))
        : undefined
    "
  >
    <fieldset :disabled="readonly" class="min-w-0 space-y-4">
      <div
        class="grid grid-cols-2 gap-2 sm:grid-cols-3"
        role="group"
        :aria-label="$t('Trigger type')"
      >
        <button
          v-for="item in catalog.triggers"
          :key="item.key"
          type="button"
          class="flex items-center gap-2 rounded-lg border p-3 text-start text-sm focus:ring-2 focus:ring-primary-500 focus:outline-hidden"
          :class="
            item.key === trigger.type
              ? 'border-primary-500 bg-primary-50 text-primary-900 dark:bg-primary-950 dark:text-primary-100'
              : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800'
          "
          :aria-pressed="item.key === trigger.type"
          @click="pick(item.key)"
        >
          <component
            :is="triggerIcon(item.key)"
            class="size-4 shrink-0"
            aria-hidden="true"
          />
          {{ item.label }}
        </button>
      </div>

      <ConfigForm
        v-if="current"
        v-model="trigger.config"
        :rules="current.config_rules"
        :options="current.config_options"
        :errors="errors"
        :choices="choices"
      />

      <WebhookUrl
        v-if="!readonly && trigger.type === 'webhook'"
        :automation="automation"
      />
    </fieldset>
  </StepCard>
</template>

<script setup lang="ts">
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';
import { useHttp } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FormField from '@/components/forms/FormField.vue';
import ModelCombobox from '@/components/forms/ModelCombobox.vue';
import { test } from '@/routes/automations';
import { lookup } from '@/routes/tickets';
import type { TestResult } from './testResult';

/**
 * A slide-over that dry-runs the automation as it is now, saved or not, against a ticket (and,
 * for a webhook trigger, a sample payload). Nothing is changed. The result goes to the flow.
 */
const { definition, deskId, webhook } = defineProps<{
  definition: App.Data.Automations.AutomationDefinitionData;
  deskId: string | null;
  /** Whether the trigger is a webhook, which takes a sample payload. */
  webhook: boolean;
}>();

const emit = defineEmits<{ result: [result: TestResult] }>();

const open = defineModel<boolean>('open', { required: true });

type Ticket = App.Data.Tickets.TicketData;

const ticketId = ref<string | null>(null);
const ticket = ref<Ticket | null>(null);
const payload = ref('');
const http = useHttp({ ticket_id: '', sample_payload: '' });

/** The payload as the server takes it: undefined while empty, null while it is not valid JSON. */
const parsedPayload = computed<Record<string, unknown> | null | undefined>(
  () => {
    if (!payload.value.trim()) {
      return undefined;
    }

    try {
      const parsed: unknown = JSON.parse(payload.value);

      return parsed !== null && typeof parsed === 'object'
        ? (parsed as Record<string, unknown>)
        : null;
    } catch {
      return null;
    }
  },
);

const canRun = computed(
  () => !!ticketId.value && (!webhook || parsedPayload.value !== null),
);

/** The errors that no field of the panel shows (a definition that does not validate, say). */
const messages = computed(() =>
  Object.entries<string>(http.errors)
    .filter(([key]) => key !== 'ticket_id' && key !== 'sample_payload')
    .map(([, message]) => message),
);
const failure = ref('');

async function run(): Promise<void> {
  failure.value = '';

  try {
    const result = (await http
      .transform(
        () =>
          ({
            definition,
            desk_id: deskId,
            ticket_id: ticketId.value,
            sample_payload: webhook ? (parsedPayload.value ?? null) : null,
          }) as never,
      )
      .post(test().url)) as TestResult;

    emit('result', result);
    open.value = false;
  } catch {
    failure.value = http.hasErrors ? '' : trans('The test could not be run.');
  }
}
</script>

<template>
  <TransitionRoot as="template" :show="open">
    <Dialog class="relative z-40" @close="open = false">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-800/75" aria-hidden="true" />
      </TransitionChild>

      <div class="fixed inset-y-0 right-0 flex w-full sm:max-w-md">
        <TransitionChild
          as="template"
          enter="transform transition duration-300 ease-out"
          enter-from="translate-x-full"
          enter-to="translate-x-0"
          leave="transform transition duration-200 ease-in"
          leave-from="translate-x-0"
          leave-to="translate-x-full"
        >
          <DialogPanel
            class="flex w-full flex-col overflow-y-auto bg-white shadow-xl dark:bg-gray-900"
          >
            <div
              class="flex items-center justify-between border-b border-gray-200 px-4 py-4 dark:border-gray-800"
            >
              <DialogTitle
                class="text-lg font-medium text-gray-900 dark:text-white"
              >
                {{ $t('Test this automation') }}
              </DialogTitle>
              <button
                type="button"
                class="rounded-full text-gray-400 hover:text-gray-500 focus:ring-3 focus:ring-gray-300 focus:outline-hidden dark:focus:ring-gray-500"
                @click="open = false"
              >
                <span class="sr-only">{{ $t('Close') }}</span>
                <X class="size-6" aria-hidden="true" />
              </button>
            </div>

            <form
              class="space-y-5 p-4"
              @submit.prevent="canRun && !http.processing && run()"
            >
              <p class="text-sm text-gray-600 dark:text-gray-300">
                {{
                  $t(
                    'Runs the automation as you see it, saved or not, against a ticket. Nothing is changed: each action only says what it would do.',
                  )
                }}
              </p>

              <FormField :error="http.errors.ticket_id" required>
                {{ $t('Ticket') }}
                <template #component="{ id, hasError }">
                  <ModelCombobox
                    :id="id"
                    v-model="ticketId"
                    v-model:object="ticket"
                    :url="lookup().url"
                    :min-length="1"
                    :has-error="hasError"
                    :display-value="
                      (item: Ticket | null) =>
                        item ? `#${item.number} ${item.subject}` : ''
                    "
                  />
                </template>
              </FormField>
              <p class="-mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ $t('Search by number or subject.') }}
              </p>

              <FormField v-if="webhook" :error="http.errors.sample_payload">
                {{ $t('Sample payload') }}
                <template #component="{ id }">
                  <AppTextarea
                    :id="id"
                    v-model="payload"
                    class="font-mono"
                    :has-error="parsedPayload === null"
                  />
                </template>
              </FormField>
              <p
                v-if="webhook"
                class="-mt-3 text-xs"
                :class="
                  parsedPayload === null
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-gray-500 dark:text-gray-400'
                "
              >
                {{
                  parsedPayload === null
                    ? $t('This is not a JSON object.')
                    : parsedPayload === undefined
                      ? $t(
                          'Paste a JSON object that a caller would send, or leave it empty.',
                        )
                      : $t('Valid JSON.')
                }}
              </p>

              <ul
                v-if="messages.length || failure"
                class="space-y-1 text-sm text-red-600 dark:text-red-400"
                role="alert"
              >
                <li v-for="message in messages" :key="message">
                  {{ message }}
                </li>
                <li v-if="failure">{{ failure }}</li>
              </ul>

              <AppButton
                type="submit"
                full
                :disabled="!canRun"
                :loading="http.processing"
              >
                {{ $t('Run test') }}
              </AppButton>
            </form>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

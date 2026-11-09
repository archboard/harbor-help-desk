<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppToggle from '@/components/forms/AppToggle.vue';
import {
  CheckboxListField,
  InputField,
  TextareaField,
} from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Modal from '@/components/modals/Modal.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import {
  destroy,
  ping,
  secret as rotateSecret,
  store,
  update,
} from '@/routes/workspace/webhooks';

defineOptions({ layout: { title: trans('Webhooks') } });

type Webhook = App.Data.Webhooks.WebhookData;

const { webhooks, eventTypes } = defineProps<{
  webhooks: Webhook[];
  deliveries: Record<string, App.Data.Webhooks.WebhookDeliveryData[]>;
  eventTypes: Record<App.Enums.TicketEventType, string>;
}>();

const page = usePage();
const { copy, copied } = useClipboard();

/** The webhook being edited, `'new'` while creating one, or null when the dialog is closed. */
const editing = ref<Webhook | 'new' | null>(null);
const deleting = ref<Webhook | null>(null);
const rotating = ref<Webhook | null>(null);
const viewing = ref<string | null>(null);
const busy = ref(false);

/** The signing secret of a new or rotated webhook, kept here until it is dismissed. */
const issued = ref<App.Data.Webhooks.WebhookSecretData | null>(null);

watch(
  () => page.flash.webhookSecret,
  (value) => {
    if (value) {
      issued.value = value;
    }
  },
  { immediate: true },
);

const viewed = computed(
  () => webhooks.find((webhook) => webhook.id === viewing.value) ?? null,
);

const form = useForm({
  url: '',
  description: '',
  event_types: [] as string[],
});

function open(webhook: Webhook | 'new'): void {
  form.clearErrors();
  form.defaults({
    url: webhook === 'new' ? '' : webhook.url,
    description: webhook === 'new' ? '' : (webhook.description ?? ''),
    event_types: webhook === 'new' ? [] : [...webhook.event_types],
  });
  form.reset();
  editing.value = webhook;
}

function save(close: () => void): void {
  const webhook = editing.value;

  form
    .transform((data) => ({ ...data, description: data.description || null }))
    .submit(
      webhook === 'new' || webhook === null ? store() : update(webhook.id),
      {
        preserveScroll: true,
        onSuccess: close,
      },
    );
}

function toggle(webhook: Webhook, isEnabled: boolean): void {
  router.patch(
    update(webhook.id).url,
    { is_enabled: isEnabled },
    { preserveScroll: true },
  );
}

function call(
  method: 'post' | 'delete',
  url: string,
  close?: () => void,
): void {
  router.visit(url, {
    method,
    preserveScroll: true,
    onStart: () => (busy.value = true),
    onFinish: () => (busy.value = false),
    onSuccess: close,
  });
}

function summary(webhook: Webhook): string {
  return webhook.event_types.length
    ? webhook.event_types.map((type) => eventTypes[type]).join(', ')
    : trans('All events');
}
</script>

<template>
  <Head :title="$t('Webhooks')" />

  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            'Harbor sends a signed JSON request to each URL when a ticket changes.',
          )
        }}
      </p>
      <AppButton type="button" class="shrink-0" @click="open('new')">{{
        $t('New webhook')
      }}</AppButton>
    </div>

    <Table v-if="webhooks.length">
      <Thead>
        <tr>
          <Th>{{ $t('URL') }}</Th>
          <Th>{{ $t('Events') }}</Th>
          <Th>{{ $t('Status') }}</Th>
          <Th>{{ $t('Enabled') }}</Th>
          <Th right
            ><span class="sr-only">{{ $t('Actions') }}</span></Th
          >
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="webhook in webhooks" :key="webhook.id">
          <Td darker>
            <span class="break-all">{{ webhook.url }}</span>
            <span
              v-if="webhook.description"
              class="block text-xs font-normal text-gray-500 dark:text-gray-400"
            >
              {{ webhook.description }}
            </span>
          </Td>
          <Td>{{ summary(webhook) }}</Td>
          <Td>
            <Pill v-if="webhook.disabled_at" color="red">{{
              $t('Switched off after failures')
            }}</Pill>
            <Pill v-else-if="!webhook.is_enabled">{{ $t('Disabled') }}</Pill>
            <Pill v-else-if="webhook.consecutive_failures" color="yellow">
              {{ webhook.consecutive_failures }} failed in a row
            </Pill>
            <Pill v-else color="green">{{ $t('Healthy') }}</Pill>
          </Td>
          <Td>
            <AppToggle
              :model-value="webhook.is_enabled"
              @update:model-value="toggle(webhook, $event)"
              >{{ $t('Enabled') }}</AppToggle
            >
          </Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="viewing = webhook.id"
              >{{ $t('Deliveries') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="white"
              :loading="busy"
              @click="call('post', ping(webhook.id).url)"
              >{{ $t('Send ping') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="rotating = webhook"
              >{{ $t('Rotate secret') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="open(webhook)"
              >{{ $t('Edit') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="red"
              @click="deleting = webhook"
              >{{ $t('Delete') }}</AppButton
            >
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      {{ $t('There are no webhooks yet.') }}
    </p>

    <section v-if="viewed" class="space-y-3">
      <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100">
        <i18n-t keypath="Recent deliveries to :url" tag="span">
          <template #url
            ><span class="break-all">{{ viewed.url }}</span></template
          >
        </i18n-t>
      </h2>
      <Table v-if="deliveries[viewed.id]?.length">
        <Thead>
          <tr>
            <Th>{{ $t('Event') }}</Th>
            <Th>{{ $t('Status') }}</Th>
            <Th>{{ $t('Response') }}</Th>
            <Th>{{ $t('Duration') }}</Th>
            <Th>{{ $t('Attempts') }}</Th>
            <Th>{{ $t('When') }}</Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="delivery in deliveries[viewed.id]" :key="delivery.id">
            <Td darker>{{ delivery.event_type }}</Td>
            <Td>
              <Pill
                :color="
                  delivery.status === 'succeeded'
                    ? 'green'
                    : delivery.status === 'failed'
                      ? 'red'
                      : 'gray'
                "
              >
                {{ delivery.status }}
              </Pill>
            </Td>
            <Td>{{ delivery.response_status ?? delivery.error ?? '—' }}</Td>
            <Td>
              {{
                delivery.duration_ms === null
                  ? '—'
                  : $t(':ms ms', { ms: String(delivery.duration_ms) })
              }}
            </Td>
            <Td>{{ delivery.attempts }}</Td>
            <Td><RelativeTime :datetime="delivery.created_at" /></Td>
          </tr>
        </Tbody>
      </Table>
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        {{ $t('Nothing has been delivered to this webhook yet.') }}
      </p>
    </section>
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? $t('New webhook') : $t('Edit webhook')"
    :action-loading="form.processing"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="form.url"
          :label="$t('URL')"
          type="url"
          :error="form.errors.url"
          required
        />
        <TextareaField
          v-model="form.description"
          :label="$t('Description')"
          :error="form.errors.description"
        />
        <CheckboxListField
          v-model="form.event_types"
          :label="$t('Events')"
          :options="eventTypes"
          :error="form.errors.event_types"
          :help-text="$t('Leave all unchecked to send every event.')"
        />
      </AppFieldset>
    </form>
  </Modal>

  <ConfirmationModal
    v-if="deleting"
    :loading="busy"
    @close="deleting = null"
    @confirmed="
      call('delete', destroy(deleting!.id).url, () => (deleting = null))
    "
  >
    <template #headline>{{ $t('Delete this webhook?') }}</template>
    {{
      $t('Nothing more is sent to :url, and its delivery history is removed.', {
        url: deleting.url,
      })
    }}
    <template #actionText>{{ $t('Delete webhook') }}</template>
  </ConfirmationModal>

  <ConfirmationModal
    v-if="rotating"
    :loading="busy"
    @close="rotating = null"
    @confirmed="
      call('post', rotateSecret(rotating!.id).url, () => (rotating = null))
    "
  >
    <template #headline>{{ $t('Rotate the secret?') }}</template>
    {{
      $t(
        'The current secret stops working at once, so the receiver needs the new one to verify signatures.',
      )
    }}
    <template #actionText>{{ $t('Rotate secret') }}</template>
  </ConfirmationModal>

  <Modal
    v-if="issued"
    :headline="$t('Signing secret')"
    size="2xl"
    hide-actions
    @close="issued = null"
  >
    <div class="space-y-3">
      <p class="text-sm text-gray-600 dark:text-gray-300">
        {{
          $t('Copy it now: the secret for :url is not shown again.', {
            url: issued.webhook.url,
          })
        }}
      </p>
      <div class="flex items-center gap-2">
        <pre
          class="min-w-0 flex-1 overflow-x-auto rounded-lg bg-gray-100 p-3 text-xs break-all whitespace-pre-wrap dark:bg-gray-900"
          >{{ issued.secret }}</pre>
        <AppButton
          type="button"
          size="xs"
          color="white"
          @click="copy(issued.secret)"
        >
          <Check v-if="copied" class="size-3" aria-hidden="true" />
          <Copy v-else class="size-3" aria-hidden="true" />
          {{ copied ? $t('Copied') : $t('Copy') }}
        </AppButton>
      </div>
    </div>
  </Modal>
</template>

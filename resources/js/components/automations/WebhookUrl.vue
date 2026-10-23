<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AppButton from '@/components/AppButton.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Modal from '@/components/modals/Modal.vue';
import { webhookToken } from '@/routes/automations';
import { automations as hook } from '@/routes/webhooks';

/**
 * The incoming URL of a webhook trigger. Only a hash of the secret is kept, so the full URL
 * is shown once, when the secret is made or rotated; until then the card shows its shape.
 */
const { automation } = defineProps<{
  /** The saved automation, or null while it is new. */
  automation: App.Data.Automations.AutomationData | null;
}>();

const http = useHttp({});
const { copy } = useClipboard();

const hasSecret = ref(automation?.has_webhook_token ?? false);
const confirming = ref(false);
const issued = ref<App.Data.Automations.AutomationWebhookTokenData | null>(
  null,
);
const copied = ref('');
const error = ref('');

watch(
  () => automation?.has_webhook_token,
  (value) => (hasSecret.value = value ?? false),
);

/** The URL's shape, with the secret left out. */
const template = computed(
  () =>
    `${window.location.origin}${hook.definition.url.replace('{automation}', automation?.id ?? '')}`,
);

const curl = computed(
  () =>
    `curl -X POST '${issued.value?.url}' \\\n  -H 'Content-Type: application/json' \\\n  -d '{"example": "payload"}'`,
);

const items = computed(() => [
  { key: 'url', label: 'URL', text: issued.value?.url ?? '' },
  { key: 'token', label: 'Secret', text: issued.value?.token ?? '' },
  { key: 'curl', label: 'Example request', text: curl.value },
]);

async function rotate(close?: () => void): Promise<void> {
  error.value = '';

  try {
    issued.value = (await http.post(
      webhookToken.url(automation!.id),
    )) as App.Data.Automations.AutomationWebhookTokenData;
    hasSecret.value = true;
    close?.();
  } catch {
    error.value =
      Object.values<string>(http.errors)[0] ??
      'The secret could not be rotated.';
    close?.();
  }
}

async function copyText(key: string, text: string): Promise<void> {
  await copy(text);
  copied.value = key;
}
</script>

<template>
  <div
    class="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700"
  >
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
      Webhook URL
    </p>

    <p v-if="!automation" class="text-sm text-gray-500 dark:text-gray-400">
      Save the automation to get its secret URL.
    </p>
    <p
      v-else-if="automation.definition.trigger.type !== 'webhook'"
      class="text-sm text-gray-500 dark:text-gray-400"
    >
      Save the new trigger to get a secret URL.
    </p>
    <template v-else>
      <p class="text-sm break-all text-gray-600 dark:text-gray-300">
        <code>{{ template }}</code>
      </p>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          hasSecret
            ? 'The secret is stored hashed, so the full URL can only be shown when you rotate it.'
            : 'No secret has been made yet, so the URL does not work.'
        }}
      </p>
      <p v-if="error" class="text-sm text-red-600 dark:text-red-400">
        {{ error }}
      </p>
      <AppButton
        type="button"
        size="xs"
        color="white"
        :loading="http.processing"
        @click="hasSecret ? (confirming = true) : rotate()"
      >
        {{ hasSecret ? 'Rotate secret' : 'Create secret' }}
      </AppButton>
    </template>
  </div>

  <ConfirmationModal
    v-if="confirming"
    :loading="http.processing"
    @close="confirming = false"
    @confirmed="rotate"
  >
    <template #headline>Rotate the secret?</template>
    The current URL stops working at once, so anything that calls it needs the
    new one.
    <template #actionText>Rotate secret</template>
  </ConfirmationModal>

  <Modal
    v-if="issued"
    headline="Your webhook URL"
    size="2xl"
    @close="issued = null"
  >
    <div class="space-y-4">
      <p class="text-sm text-gray-600 dark:text-gray-300">
        Copy it now: the secret is not shown again.
      </p>

      <div v-for="item in items" :key="item.key" class="space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ item.label }}
          </span>
          <AppButton
            type="button"
            size="xs"
            color="white"
            @click="copyText(item.key, item.text)"
          >
            <Check
              v-if="copied === item.key"
              class="size-3"
              aria-hidden="true"
            />
            <Copy v-else class="size-3" aria-hidden="true" />
            {{ copied === item.key ? 'Copied' : 'Copy' }}
          </AppButton>
        </div>
        <pre
          class="overflow-x-auto rounded-lg bg-gray-100 p-3 text-xs break-all whitespace-pre-wrap dark:bg-gray-900"
          >{{ item.text }}</pre>
      </div>
    </div>
  </Modal>
</template>

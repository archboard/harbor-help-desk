<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { InputField, SelectField } from '@/components/forms/fields';
import PlaceholderTextarea from '../PlaceholderTextarea.vue';
import { optionsOf } from './types';
import type { ActionEditorProps } from './types';

/**
 * The "Send a webhook" action. The URL and header values take placeholders, and the body is JSON
 * whose placeholders stand for text. The server refuses addresses it can't reach safely.
 */
const { catalog, trigger, errors = {} } = defineProps<ActionEditorProps>();

const MAX_HEADERS = 10;

const config = defineModel<{
  method?: string;
  url?: string;
  headers?: { name: string; value: string }[] | null;
  body?: string | null;
}>({ required: true });

const headers = computed(() => config.value.headers ?? []);

const headersError = computed(
  () =>
    errors.headers ??
    Object.entries(errors).find(([key]) => key.startsWith('headers.'))?.[1],
);

function setHeaders(next: { name: string; value: string }[]): void {
  config.value = { ...config.value, headers: next.length ? next : null };
}

function setHeader(
  index: number,
  patch: Partial<{ name: string; value: string }>,
): void {
  setHeaders(
    headers.value.map((header, i) =>
      i === index ? { ...header, ...patch } : header,
    ),
  );
}

/** Placeholders stand for a number, as on the server, so they are valid anywhere in JSON. */
const jsonState = computed<'empty' | 'valid' | 'invalid'>(() => {
  const body = config.value.body?.trim();

  if (!body) {
    return 'empty';
  }

  try {
    JSON.parse(body.replace(/\{\{[^}]*\}\}/g, '0'));

    return 'valid';
  } catch {
    return 'invalid';
  }
});
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
      <SelectField
        v-model="config.method"
        :label="$t('Method')"
        required
        :options="optionsOf(catalog, 'send_webhook', 'method')"
        :error="errors.method"
      />
      <InputField
        v-model="config.url"
        :label="$t('URL')"
        required
        placeholder="https://example.com/hook"
        :help-text="
          $t(
            'http or https. Placeholders such as {{ ticket.number }} are filled in, URL-encoded.',
          )
        "
        :error="errors.url"
      />
    </div>

    <div class="space-y-2">
      <AppLabel :has-error="!!headersError">{{ $t('Headers') }}</AppLabel>
      <div
        v-for="(header, index) in headers"
        :key="index"
        class="flex items-center gap-2"
      >
        <AppInput
          class="w-40"
          :aria-label="$t('Header name')"
          placeholder="X-Api-Key"
          :has-error="!!errors[`headers.${index}.name`]"
          :model-value="header.name"
          @update:model-value="setHeader(index, { name: String($event ?? '') })"
        />
        <AppInput
          class="flex-1"
          :aria-label="$t('Header value')"
          :placeholder="$t('Value')"
          :has-error="!!errors[`headers.${index}.value`]"
          :model-value="header.value"
          @update:model-value="
            setHeader(index, { value: String($event ?? '') })
          "
        />
        <AppButton
          type="button"
          size="xs"
          color="red"
          @click="setHeaders(headers.filter((_, i) => i !== index))"
        >
          <Trash2 class="size-3" aria-hidden="true" />
          <span class="sr-only">{{ $t('Remove header') }}</span>
        </AppButton>
      </div>
      <FieldError v-if="headersError">{{ headersError }}</FieldError>
      <AppButton
        v-if="headers.length < MAX_HEADERS"
        type="button"
        size="xs"
        color="gray_light"
        @click="setHeaders([...headers, { name: '', value: '' }])"
      >
        <Plus class="size-3" aria-hidden="true" />
        {{ $t('Add header') }}
      </AppButton>
    </div>

    <div class="space-y-1">
      <PlaceholderTextarea
        v-model="config.body"
        mono
        :catalog="catalog"
        :trigger="trigger"
        :label="$t('Body')"
        :help-text="
          $t(
            'JSON. A placeholder is filled in as text, so put it inside quotes.',
          )
        "
        :error="errors.body"
      />
      <p
        v-if="jsonState !== 'empty'"
        class="text-sm font-medium"
        :class="
          jsonState === 'valid'
            ? 'text-green-700 dark:text-green-400'
            : 'text-red-600 dark:text-red-400'
        "
      >
        {{ jsonState === 'valid' ? $t('Valid JSON') : $t('Not valid JSON') }}
      </p>
    </div>
  </div>
</template>

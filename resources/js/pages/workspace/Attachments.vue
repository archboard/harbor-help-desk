<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FieldError from '@/components/forms/FieldError.vue';
import HelpText from '@/components/forms/HelpText.vue';
import { NumberField } from '@/components/forms/fields';
import Pill from '@/components/Pill.vue';
import { update as updateSettings } from '@/routes/workspace/settings';

defineOptions({ layout: { title: 'Attachments' } });

const { maxSizeKb } = defineProps<{
  /** The limit that applies now: the setting held down by the platform cap and PHP. */
  maxSizeKb: number;
  multitenant: boolean;
  platformBlockedExtensions: string[];
}>();

const settings = usePage().props.workspace?.settings;

const form = useForm({
  attachments_max_size_kb: settings?.attachments_max_size_kb ?? 25600,
  attachments_blocked_extensions: [
    ...(settings?.attachments_blocked_extensions ?? []),
  ],
});

const maxSizeMb = computed({
  get: () => form.attachments_max_size_kb / 1024,
  set: (mb: number | null) =>
    (form.attachments_max_size_kb = Math.round((mb ?? 0) * 1024)),
});

const serverLimited = computed(() => maxSizeKb < form.attachments_max_size_kb);

/** Item errors come back keyed by index, e.g. `attachments_blocked_extensions.0`. */
const extensionsError = computed(
  () =>
    Object.entries(form.errors).find(([key]) =>
      key.startsWith('attachments_blocked_extensions'),
    )?.[1],
);

const newExtension = ref('');

function addExtension(): void {
  const extension = newExtension.value.trim().toLowerCase().replace(/^\./, '');

  if (extension && !form.attachments_blocked_extensions.includes(extension)) {
    form.attachments_blocked_extensions.push(extension);
  }

  newExtension.value = '';
}
</script>

<template>
  <Head title="Attachments" />

  <CardWrapper>
    <form
      @submit.prevent="form.submit(updateSettings(), { preserveScroll: true })"
    >
      <CardPadding>
        <AppFieldset>
          <NumberField
            v-model="maxSizeMb"
            label="Largest file (MB)"
            :help-text="
              serverLimited
                ? `Server limit: ${+(maxSizeKb / 1024).toFixed(2)} MB. Larger files are rejected.`
                : undefined
            "
            :error="form.errors.attachments_max_size_kb"
            :min="0.001"
            required
          />

          <div class="space-y-2">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">
              Blocked file types
            </h3>
            <div class="flex flex-wrap gap-1.5">
              <Pill
                v-for="(
                  extension, index
                ) in form.attachments_blocked_extensions"
                :key="extension"
              >
                .{{ extension }}
                <button
                  type="button"
                  class="-mr-1 ml-1 rounded-full hover:text-red-600"
                  @click="form.attachments_blocked_extensions.splice(index, 1)"
                >
                  <span class="sr-only">Unblock {{ extension }}</span>
                  <X class="size-3" aria-hidden="true" />
                </button>
              </Pill>
            </div>
            <AppInput
              v-model="newExtension"
              placeholder="Add an extension and press Enter"
              aria-label="Block an extension"
              @keydown.enter.prevent="addExtension"
            />
            <HelpText v-if="multitenant">
              These are blocked as well:
              {{ platformBlockedExtensions.join(', ') }}.
            </HelpText>
            <FieldError v-if="extensionsError">
              {{ extensionsError }}
            </FieldError>
          </div>
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

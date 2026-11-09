<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Menu, MenuButton, MenuItem } from '@headlessui/vue';
import { Braces } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, nextTick, useTemplateRef } from 'vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import FormField from '@/components/forms/FormField.vue';

/**
 * A textarea with an "Insert placeholder" menu of the placeholders the trigger offers, on top of
 * the ones every text can use. A placeholder goes in at the caret as `{{ key }}`.
 */
const { catalog, trigger, label, error, required, helpText, mono } =
  defineProps<{
    catalog: App.Data.Automations.AutomationCatalogData;
    /** The trigger's key. */
    trigger: string;
    label: string;
    error?: string;
    required?: boolean;
    helpText?: string;
    /** Monospace, for code such as JSON. */
    mono?: boolean;
  }>();

const model = defineModel<string | null>();
const box = useTemplateRef<HTMLElement>('box');

const triggerOnly = computed(
  () =>
    catalog.triggers.find((item) => item.key === trigger)?.placeholders ?? [],
);

const groups = computed(() =>
  [
    { label: trans('Ticket'), items: catalog.placeholders },
    { label: trans('This trigger'), items: triggerOnly.value },
  ].filter((group) => group.items.length),
);

async function insert(key: string): Promise<void> {
  const textarea = box.value?.querySelector('textarea');
  const text = model.value ?? '';
  const start = textarea?.selectionStart ?? text.length;
  const end = textarea?.selectionEnd ?? text.length;
  const token = `{{ ${key} }}`;

  model.value = text.slice(0, start) + token + text.slice(end);

  await nextTick();
  textarea?.focus();
  textarea?.setSelectionRange(start + token.length, start + token.length);
}
</script>

<template>
  <div ref="box" :class="{ '[&_textarea]:font-mono': mono }">
    <FormField
      v-model="model"
      component="AppTextarea"
      :error="error"
      :required="required"
    >
      {{ label }}
      <template #after>
        <div class="flex items-start justify-between gap-2">
          <p v-if="helpText" class="text-sm text-gray-500 dark:text-gray-400">
            {{ helpText }}
          </p>
          <Menu as="div" class="relative ms-auto">
            <Float
              placement="bottom-end"
              :offset="4"
              flip
              portal
              enter="transition duration-100 ease-out"
              enter-from="scale-95 opacity-0"
              enter-to="scale-100 opacity-100"
              leave="transition duration-75 ease-in"
              leave-from="scale-100 opacity-100"
              leave-to="scale-95 opacity-0"
            >
              <MenuButton
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:text-gray-300 dark:hover:bg-gray-800"
              >
                <Braces class="size-3.5" aria-hidden="true" />
                {{ $t('Insert placeholder') }}
              </MenuButton>
              <AppMenuItems>
                <div v-for="group in groups" :key="group.label" class="py-1">
                  <p
                    class="px-4 py-1 text-xs font-semibold text-gray-500 uppercase dark:text-gray-400"
                  >
                    {{ group.label }}
                  </p>
                  <MenuItem
                    v-for="item in group.items"
                    :key="item.key"
                    v-slot="{ active }"
                  >
                    <button
                      type="button"
                      class="block w-full px-4 py-1.5 text-start text-sm text-gray-700 dark:text-gray-200"
                      :class="{ 'bg-gray-100 dark:bg-gray-700': active }"
                      @click="insert(item.key)"
                    >
                      {{ item.label }}
                    </button>
                  </MenuItem>
                </div>
              </AppMenuItems>
            </Float>
          </Menu>
        </div>
      </template>
    </FormField>
  </div>
</template>

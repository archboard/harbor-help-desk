<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref, useId } from 'vue';
import AppInput from '@/components/forms/AppInput.vue';
import FormField from '@/components/forms/FormField.vue';
import Pill from '@/components/Pill.vue';

/**
 * A list of free-text values as removable pills. A native datalist suggests `options`
 * (the Enter key or leaving the box adds what was typed).
 */
const {
  label,
  error,
  required = false,
  options = [],
} = defineProps<{
  label?: string;
  error?: string;
  required?: boolean;
  options?: App.Data.Automations.CatalogOptionData[];
}>();

const model = defineModel<string[]>({ default: () => [] });

const draft = ref('');
const listId = useId();

function add(): void {
  const text = draft.value.trim();

  if (text && !model.value.includes(text)) {
    model.value = [...model.value, text];
  }

  draft.value = '';
}
</script>

<template>
  <FormField :error="error" :required="required">
    {{ label }}
    <template #component>
      <div class="space-y-2">
        <div v-if="model.length" class="flex flex-wrap gap-1">
          <Pill v-for="item in model" :key="item" color="primary">
            {{ options.find((option) => option.value === item)?.label ?? item }}
            <button
              type="button"
              class="-mr-1 ml-1 rounded-full hover:text-red-600"
              @click="model = model.filter((other) => other !== item)"
            >
              <span class="sr-only">Remove {{ item }}</span>
              <X class="size-3" aria-hidden="true" />
            </button>
          </Pill>
        </div>
        <AppInput
          v-model="draft"
          :list="listId"
          :has-error="!!error"
          placeholder="Type a value and press Enter"
          :aria-label="label ?? 'Add a value'"
          @keydown.enter.prevent="add"
          @change="add"
        />
        <datalist :id="listId">
          <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </datalist>
      </div>
    </template>
  </FormField>
</template>

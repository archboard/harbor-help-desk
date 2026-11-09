<script setup lang="ts">
import { Calendar, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import AppInput from '@/components/forms/AppInput.vue';

const {
  id,
  hasError = false,
  disabled = false,
  mode = 'date',
} = defineProps<{
  id?: string;
  hasError?: boolean;
  disabled?: boolean;
  mode?: 'date' | 'datetime';
}>();

const model = defineModel<string | null>();

// ponytail: the app speaks 'YYYY-MM-DD HH:mm', the native input speaks 'YYYY-MM-DDTHH:mm'
const inputValue = computed({
  get: () => (model.value ?? '').replace(' ', 'T'),
  set: (value: string | number | null | undefined) => {
    model.value = value ? String(value).replace('T', ' ') : null;
  },
});
</script>

<template>
  <div class="relative">
    <div
      :class="[
        'pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3',
        hasError ? 'text-red-500' : 'text-gray-500',
      ]"
    >
      <Calendar class="size-4" />
    </div>

    <AppInput
      :id="id"
      v-model="inputValue"
      class="pl-10"
      :type="mode === 'datetime' ? 'datetime-local' : 'date'"
      :has-error="hasError"
      :disabled="disabled"
    />

    <div
      class="absolute inset-y-0 right-0 flex items-center justify-center"
      :class="hasError ? 'pr-10' : 'pr-4'"
    >
      <button
        v-if="model && !disabled"
        type="button"
        class="flex items-center justify-center"
        @click.prevent="model = null"
      >
        <span class="sr-only">{{ $t('Clear date') }}</span>
        <Trash2 class="size-4 text-red-500" />
      </button>
    </div>
  </div>
</template>

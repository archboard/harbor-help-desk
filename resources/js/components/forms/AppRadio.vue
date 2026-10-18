<script setup lang="ts">
import { useId } from 'vue';

const {
  value,
  name,
  disabled = false,
} = defineProps<{
  value: string | number | boolean;
  name?: string;
  disabled?: boolean;
}>();

const model = defineModel<string | number | boolean | null>();
const id = useId();
</script>

<template>
  <div class="flex items-center">
    <input
      :id="id"
      v-model="model"
      :name="name"
      type="radio"
      :value="value"
      :disabled="disabled"
      class="size-4 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
    />
    <label
      :for="id"
      class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-200"
    >
      <slot />
    </label>
  </div>
</template>

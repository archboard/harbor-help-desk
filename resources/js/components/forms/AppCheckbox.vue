<script setup lang="ts">
import Req from '@/components/forms/Req.vue';

const {
  value = null,
  required = false,
  disabled = false,
} = defineProps<{
  value?: string | number | boolean | null;
  required?: boolean;
  disabled?: boolean;
}>();

const model = defineModel<boolean | Array<string | number | boolean>>({
  default: false,
});
</script>

<template>
  <label
    class="inline-flex items-center gap-2"
    :class="{ 'cursor-not-allowed': disabled }"
  >
    <input
      v-model="model"
      type="checkbox"
      :value="value"
      :disabled="disabled"
      class="cursor-pointer rounded-sm border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:focus:ring-offset-black"
    />
    <span v-if="$slots.default" class="cursor-pointer text-sm select-none">
      <slot />
      <Req v-if="required" />
    </span>
  </label>
</template>

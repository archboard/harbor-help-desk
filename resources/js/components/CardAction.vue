<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';

const { negativeMargin = false, loading = false } = defineProps<{
  negativeMargin?: boolean;
  loading?: boolean;
}>();
</script>

<template>
  <div
    class="rounded-b-2xl bg-gray-50 dark:bg-gray-800"
    :class="{ '-mx-4 mt-6 -mb-5 sm:-mx-6': negativeMargin }"
  >
    <CardPadding class="flex justify-end gap-2">
      <slot>
        <AppButton type="submit" :loading="loading">Save</AppButton>
      </slot>
    </CardPadding>
  </div>
</template>

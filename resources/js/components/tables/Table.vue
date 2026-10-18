<script setup lang="ts">
const { hideShadow = false, noTopRadius = false } = defineProps<{
  hideShadow?: boolean;
  noTopRadius?: boolean;
}>();
</script>

<template>
  <div class="flex flex-col">
    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
        <div
          :class="[
            'overflow-hidden',
            { 'shadow-sm': !hideShadow },
            noTopRadius ? 'rounded-b-xl' : 'rounded-xl',
          ]"
        >
          <table
            class="min-w-full divide-y divide-gray-300 dark:divide-gray-700"
          >
            <slot />
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const { show } = defineProps<{ show: boolean }>();

defineEmits<{ close: [] }>();
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-40 overflow-y-auto">
      <div
        class="flex min-h-screen items-start justify-center px-4 pt-12 pb-20 text-center"
      >
        <Transition
          enter-active-class="duration-300 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-if="show"
            class="fixed inset-0 backdrop-blur-xs transition-opacity"
            aria-hidden="true"
            @click="$emit('close')"
          >
            <div class="absolute inset-0 bg-gray-800 opacity-75" />
          </div>
        </Transition>

        <slot />
      </div>
    </div>
  </Teleport>
</template>

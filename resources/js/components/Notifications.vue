<script setup lang="ts">
import Notification from '@/components/Notification.vue';
import { notifications } from '@/lib/notifications';
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed top-0 right-0 z-50 max-h-screen w-full max-w-sm space-y-4 px-4"
      aria-live="polite"
    >
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
        move-class="transition-all duration-150 ease-in"
      >
        <Notification
          v-for="notification in notifications"
          :key="notification.id"
          :notification="notification"
        />
      </TransitionGroup>
    </div>
  </Teleport>
</template>

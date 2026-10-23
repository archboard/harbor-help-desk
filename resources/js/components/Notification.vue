<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, X } from '@lucide/vue';
import type { AppNotification } from '@/lib/notifications';
import { removeNotification } from '@/lib/notifications';

const { notification } = defineProps<{ notification: AppNotification }>();

const styles = {
  success: { icon: CircleCheck, color: 'text-green-400', title: 'Success!' },
  error: { icon: CircleAlert, color: 'text-red-400', title: 'Error!' },
  warning: { icon: CircleAlert, color: 'text-yellow-400', title: 'Warning!' },
  neutral: { icon: Info, color: 'text-gray-400', title: 'Hey!' },
};
const style = styles[notification.level];
</script>

<template>
  <div
    class="pointer-events-auto mt-4 w-full overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-700"
  >
    <div class="p-4">
      <div class="flex items-start">
        <div class="shrink-0">
          <component :is="style.icon" class="size-6" :class="style.color" />
        </div>
        <div class="ml-3 w-0 flex-1 pt-0.5">
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ notification.title ?? style.title }}
          </p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
            {{ notification.text }}
          </p>
          <button
            v-if="notification.action"
            type="button"
            class="mt-2 text-sm font-medium text-primary-600 hover:text-primary-500 focus:outline-hidden focus-visible:underline dark:text-primary-400"
            @click="
              notification.action.onClick();
              removeNotification(notification.id);
            "
          >
            {{ notification.action.label }}
          </button>
        </div>
        <div class="ml-4 flex shrink-0">
          <button
            type="button"
            class="inline-flex rounded-full p-0.5 text-gray-400 hover:bg-gray-50 hover:text-gray-500 focus:bg-gray-100 focus:ring-2 focus:ring-gray-300 focus:outline-hidden dark:text-gray-300 dark:hover:bg-gray-600"
            @click.prevent="removeNotification(notification.id)"
          >
            <span class="sr-only">Close</span>
            <X class="size-5" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

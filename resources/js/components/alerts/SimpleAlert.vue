<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, X } from '@lucide/vue';
import { ref } from 'vue';
import FadeIn from '@/components/transitions/FadeIn.vue';
import type { AlertLevel } from '@/types';

const { level = 'neutral' } = defineProps<{ level?: AlertLevel }>();

const show = ref(true);

const icons = {
  success: CircleCheck,
  neutral: Info,
  warning: CircleAlert,
  error: CircleAlert,
};

const colors: Record<
  AlertLevel,
  { bg: string; icon: string; text: string; dismiss: string }
> = {
  success: {
    bg: 'bg-green-50 dark:bg-green-900',
    icon: 'text-green-400',
    text: 'text-green-800 dark:text-green-100',
    dismiss:
      'bg-green-50 text-green-500 hover:bg-green-100 focus:ring-green-600 focus:ring-offset-green-50 dark:bg-green-900 dark:hover:bg-green-800 dark:focus:ring-offset-green-900',
  },
  neutral: {
    bg: 'bg-gray-50 dark:bg-gray-900',
    icon: 'text-gray-400',
    text: 'text-gray-800 dark:text-white',
    dismiss:
      'bg-gray-50 text-gray-500 hover:bg-gray-100 focus:ring-gray-600 focus:ring-offset-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-800',
  },
  error: {
    bg: 'bg-red-50 dark:bg-red-900',
    icon: 'text-red-400',
    text: 'text-red-800 dark:text-red-100',
    dismiss:
      'bg-red-50 text-red-500 hover:bg-red-100 focus:ring-red-600 focus:ring-offset-red-50 dark:bg-red-900 dark:hover:bg-red-800 dark:focus:ring-offset-red-900',
  },
  warning: {
    bg: 'bg-yellow-50 dark:bg-yellow-900',
    icon: 'text-yellow-400',
    text: 'text-yellow-800 dark:text-yellow-100',
    dismiss:
      'bg-yellow-50 text-yellow-500 hover:bg-yellow-100 focus:ring-yellow-600 focus:ring-offset-yellow-50 dark:bg-yellow-900 dark:hover:bg-yellow-800 dark:focus:ring-offset-yellow-900',
  },
};
</script>

<template>
  <FadeIn>
    <div v-if="show" class="rounded-lg p-4 shadow-sm" :class="colors[level].bg">
      <div class="flex">
        <div class="flex shrink-0">
          <component
            :is="icons[level]"
            class="size-5"
            :class="colors[level].icon"
            aria-hidden="true"
          />
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium" :class="colors[level].text">
            <slot />
          </p>
        </div>
        <div class="ml-auto pl-3">
          <div class="-mx-1.5 -my-1.5">
            <button
              type="button"
              class="inline-flex rounded-lg p-1.5 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
              :class="colors[level].dismiss"
              @click.prevent="show = false"
            >
              <span class="sr-only">{{ $t('Dismiss') }}</span>
              <X class="size-5" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </FadeIn>
</template>

<script setup lang="ts">
import { unrefElement } from '@vueuse/core';
import type { Component } from 'vue';
import { reactive, useTemplateRef, watch } from 'vue';
import Spinner from '@/components/Spinner.vue';
import type { ButtonColor, ButtonSize } from '@/types';

const {
  component = 'button',
  size = 'base',
  color = 'primary',
  loading = false,
  full = false,
} = defineProps<{
  component?: string | Component;
  size?: ButtonSize;
  color?: ButtonColor;
  loading?: boolean;
  full?: boolean;
}>();

const sizes: Record<ButtonSize, string> = {
  xs: 'rounded-md px-2.5 py-1 text-xs leading-4',
  sm: 'rounded-md px-3 py-1.5 text-sm leading-4',
  base: 'rounded-lg px-4 py-2 text-sm leading-5',
  lg: 'rounded-xl px-4 py-2 leading-6 sm:py-3',
  xl: 'rounded-2xl px-4 py-3 leading-6 sm:px-6 sm:text-lg',
};

const colors: Record<ButtonColor, string> = {
  primary:
    'bg-primary-600 text-white hover:bg-primary-500 focus:ring-primary-500 active:bg-primary-700',
  red: 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-400 active:bg-red-700',
  yellow:
    'bg-yellow-600 text-white hover:bg-yellow-500 focus:ring-yellow-400 active:bg-yellow-700',
  green:
    'bg-green-600 text-white hover:bg-green-500 focus:ring-green-400 active:bg-green-700',
  gray: 'bg-gray-500 text-white hover:bg-gray-600 focus:ring-gray-400 active:bg-gray-700',
  gray_light:
    'bg-gray-300 text-white hover:bg-gray-500 focus:ring-gray-200 active:bg-gray-500',
  white:
    'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-400 active:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700',
};

const root = useTemplateRef<HTMLElement | Component>('root');
const lockedSize = reactive({ width: '', height: '' });

// Lock the current size so the button doesn't shrink when the label swaps for the spinner
watch(
  () => loading,
  () => {
    const el = unrefElement(root.value as HTMLElement);

    lockedSize.width = loading && !full && el ? `${el.offsetWidth}px` : '';
    lockedSize.height = loading && el ? `${el.offsetHeight}px` : '';
  },
  { flush: 'pre' },
);
</script>

<template>
  <component
    :is="component"
    ref="root"
    class="relative z-0 cursor-pointer items-center justify-center overflow-hidden border border-transparent text-center font-medium shadow-sm transition duration-150 ease-in-out hover:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none disabled:opacity-50 dark:focus:ring-offset-gray-900"
    :class="[sizes[size], colors[color], full ? 'flex w-full' : 'inline-flex']"
    :style="lockedSize"
    :disabled="loading"
  >
    <Transition
      enter-active-class="transition duration-100 ease-in-out"
      enter-from-class="-translate-x-6 opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-100 ease-in-out"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="-translate-x-6 opacity-0"
    >
      <div v-show="loading" class="inline-flex items-center">
        <Spinner :class="['lg', 'xl'].includes(size) ? 'size-6' : 'size-5'" />
      </div>
    </Transition>

    <Transition
      enter-active-class="transition duration-100 ease-in-out"
      enter-from-class="translate-x-6 opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-100 ease-in-out"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-6 opacity-0"
    >
      <span
        v-show="!loading"
        class="inline-flex items-center gap-1 whitespace-nowrap"
      >
        <slot>Save</slot>
      </span>
    </Transition>
  </component>
</template>

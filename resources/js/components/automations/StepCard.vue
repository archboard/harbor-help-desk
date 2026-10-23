<script setup lang="ts">
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  GripVertical,
  Trash2,
} from '@lucide/vue';
import type { Component } from 'vue';
import AppButton from '@/components/AppButton.vue';

/**
 * A flow card, draggable by its grip when `movable`: the header is a button that expands the body (Enter or Space), Escape
 * collapses it and Delete on the header removes the step.
 */
const {
  icon,
  kind,
  summary,
  details,
  invalid = false,
  errors = [],
  movable = false,
  canMoveUp = true,
  canMoveDown = true,
  removable = false,
} = defineProps<{
  icon: Component;
  kind: string;
  summary: string;
  /** A second line shown while collapsed, e.g. the config at a glance. */
  details?: string;
  invalid?: boolean;
  errors?: string[];
  movable?: boolean;
  canMoveUp?: boolean;
  canMoveDown?: boolean;
  removable?: boolean;
}>();

const emit = defineEmits<{ move: [offset: -1 | 1]; remove: [] }>();

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
  <div
    class="rounded-xl border bg-white shadow-sm dark:bg-gray-900"
    :class="
      invalid
        ? 'border-red-400 ring-1 ring-red-400 dark:border-red-500 dark:ring-red-500'
        : 'border-gray-200 dark:border-gray-700'
    "
    @keydown.esc.stop="open = false"
  >
    <div class="flex items-center gap-2 pe-3">
      <GripVertical
        v-if="movable"
        class="drag-handle ms-2 size-4 shrink-0 cursor-grab text-gray-400"
        aria-hidden="true"
      />
      <button
        type="button"
        class="flex min-w-0 flex-1 items-center gap-3 rounded-xl p-4 text-start focus:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500"
        :aria-expanded="open"
        @click="open = !open"
        @keydown.delete.self.prevent="removable && emit('remove')"
      >
        <component
          :is="icon"
          class="size-5 shrink-0 text-gray-500 dark:text-gray-400"
          aria-hidden="true"
        />
        <span class="min-w-0 flex-1">
          <span
            class="block text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
          >
            {{ kind }}
          </span>
          <span
            class="block truncate text-sm font-medium text-gray-900 dark:text-white"
          >
            {{ summary }}
          </span>
          <span
            v-if="details && !open"
            class="block truncate text-sm text-gray-500 dark:text-gray-400"
          >
            {{ details }}
          </span>
        </span>
        <ChevronDown
          class="size-4 shrink-0 text-gray-400 transition-transform"
          :class="{ 'rotate-180': open }"
          aria-hidden="true"
        />
      </button>

      <template v-if="movable">
        <AppButton
          type="button"
          size="xs"
          color="white"
          :disabled="!canMoveUp"
          @click="emit('move', -1)"
        >
          <ArrowUp class="size-3" aria-hidden="true" />
          <span class="sr-only">Move up</span>
        </AppButton>
        <AppButton
          type="button"
          size="xs"
          color="white"
          :disabled="!canMoveDown"
          @click="emit('move', 1)"
        >
          <ArrowDown class="size-3" aria-hidden="true" />
          <span class="sr-only">Move down</span>
        </AppButton>
      </template>
      <AppButton
        v-if="removable"
        type="button"
        size="xs"
        color="red"
        @click="emit('remove')"
      >
        <Trash2 class="size-3" aria-hidden="true" />
        <span class="sr-only">Delete step</span>
      </AppButton>
    </div>

    <ul
      v-if="errors.length"
      class="space-y-1 px-4 pb-3 text-sm text-red-600 dark:text-red-400"
    >
      <li v-for="(message, index) in errors" :key="index">{{ message }}</li>
    </ul>

    <div v-if="open" class="border-t border-gray-200 p-4 dark:border-gray-700">
      <slot />
    </div>
  </div>
</template>

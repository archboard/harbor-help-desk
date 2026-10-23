<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Menu, MenuButton, MenuItem } from '@headlessui/vue';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import { addableStepTypes } from './steps';
import type { StepType } from './steps';

/**
 * The connector between two cards, with a "+" that picks the type of a new step. A stop
 * ends the run, so it is only offered at the end of a list that has none.
 */
const { allowStop = false } = defineProps<{ allowStop?: boolean }>();

const emit = defineEmits<{ add: [type: StepType] }>();

const types = computed(() =>
  addableStepTypes.filter(([type]) => allowStop || type !== 'stop'),
);
</script>

<template>
  <div class="relative flex h-10 justify-center">
    <div
      class="absolute inset-y-0 w-px bg-gray-300 dark:bg-gray-600"
      aria-hidden="true"
    />
    <Menu as="div" class="relative flex items-center">
      <Float
        placement="bottom"
        :offset="4"
        flip
        portal
        enter="transition duration-100 ease-out"
        enter-from="scale-95 opacity-0"
        enter-to="scale-100 opacity-100"
        leave="transition duration-75 ease-in"
        leave-from="scale-100 opacity-100"
        leave-to="scale-95 opacity-0"
      >
        <MenuButton
          class="relative inline-flex size-6 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500 hover:border-primary-500 hover:text-primary-600 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:border-gray-600 dark:bg-gray-800"
        >
          <Plus class="size-4" aria-hidden="true" />
          <span class="sr-only">Add a step</span>
        </MenuButton>
        <AppMenuItems>
          <MenuItem
            v-for="[type, meta] in types"
            :key="type"
            v-slot="{ active }"
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-gray-700 dark:text-gray-200"
              :class="{ 'bg-gray-100 dark:bg-gray-700': active }"
              @click="emit('add', type)"
            >
              <component :is="meta.icon" class="size-4" aria-hidden="true" />
              {{ meta.label }}
            </button>
          </MenuItem>
        </AppMenuItems>
      </Float>
    </Menu>
  </div>
</template>

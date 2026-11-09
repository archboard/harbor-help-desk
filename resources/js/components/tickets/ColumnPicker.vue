<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { Columns3 } from '@lucide/vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import type { TicketColumn } from '@/lib/ticketColumns';

/** A popover of checkboxes choosing which columns the ticket table shows. */
defineProps<{ available: TicketColumn[] }>();

const columns = defineModel<string[]>({ required: true });
</script>

<template>
  <Popover class="relative">
    <Float
      placement="bottom-end"
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
      <PopoverButton
        class="rounded-md p-1 hover:bg-black/10 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:hover:bg-white/10"
        title="Columns"
      >
        <Columns3 class="size-4" aria-hidden="true" />
        <span class="sr-only">Choose columns</span>
      </PopoverButton>
      <PopoverPanel
        class="flex max-h-80 w-56 flex-col gap-2 overflow-auto rounded-md bg-white p-3 text-start font-normal tracking-normal shadow-lg ring-1 ring-black/5 focus:outline-hidden dark:bg-gray-800 dark:ring-white/10"
      >
        <div v-for="column in available" :key="column.id">
          <AppCheckbox v-model="columns" :value="column.id">
            {{ column.label }}
          </AppCheckbox>
        </div>
      </PopoverPanel>
    </Float>
  </Popover>
</template>

<script setup lang="ts">
import {
  Dialog,
  DialogPanel,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';
import { usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import TicketDetail from '@/components/tickets/TicketDetail.vue';
import { closeQuickView } from '@/lib/quickView';

/** A slide-over with the whole ticket, shown while the `preview` shared prop is set. */
const page = usePage();
const preview = computed(() => page.props.preview ?? null);
</script>

<template>
  <TransitionRoot as="template" :show="preview !== null">
    <Dialog class="relative z-50" @close="closeQuickView">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-800/75 backdrop-blur-xs" />
      </TransitionChild>

      <div class="fixed inset-0 flex justify-end">
        <TransitionChild
          as="template"
          enter="transform transition duration-300 ease-out"
          enter-from="translate-x-full"
          enter-to="translate-x-0"
          leave="transform transition duration-200 ease-in"
          leave-from="translate-x-0"
          leave-to="translate-x-full"
        >
          <DialogPanel
            class="relative w-full max-w-2xl overflow-y-auto bg-gray-50 p-6 shadow-xl dark:bg-black"
          >
            <button
              type="button"
              class="absolute top-4 right-4 rounded-full text-gray-400 hover:text-gray-500 focus:ring-3 focus:ring-gray-300 focus:outline-hidden dark:focus:ring-gray-500"
              @click="closeQuickView"
            >
              <span class="sr-only">{{ $t('Close') }}</span>
              <X class="size-6" aria-hidden="true" />
            </button>

            <TicketDetail
              v-if="preview"
              :key="preview.ticket.id"
              v-bind="preview"
              compact
              :reload-only="['preview']"
            />
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

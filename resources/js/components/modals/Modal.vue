<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { X } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import ModalHeadline from '@/components/modals/ModalHeadline.vue';
import ModalWrapper from '@/components/modals/ModalWrapper.vue';
import DropIn from '@/components/transitions/DropIn.vue';
import type { ButtonColor } from '@/types';

type ModalSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';

const {
  headline,
  actionText = trans('Save'),
  actionLoading = false,
  actionColor = 'primary',
  autoClose = false,
  size = 'lg',
  hideActions = false,
} = defineProps<{
  headline?: string;
  actionText?: string;
  actionLoading?: boolean;
  actionColor?: ButtonColor;
  autoClose?: boolean;
  size?: ModalSize;
  hideActions?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  action: [close: () => void];
}>();

const sizes: Record<ModalSize, string> = {
  xs: 'sm:max-w-xs',
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-md',
  lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl',
  '2xl': 'sm:max-w-2xl',
  '3xl': 'sm:max-w-3xl',
  '4xl': 'sm:max-w-4xl',
};

const show = ref(false);

function close(): void {
  show.value = false;
}

function performAction(): void {
  emit('action', close);

  if (autoClose) {
    close();
  }
}

function closeOnEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.stopPropagation();
    close();
  }
}

onMounted(() => {
  show.value = true;
  document.addEventListener('keydown', closeOnEscape);
  document.body.classList.add('overflow-hidden');
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
  document.body.classList.remove('overflow-hidden');
});

defineExpose({ close });
</script>

<template>
  <ModalWrapper :show="show" @close="close">
    <DropIn @after-leave="$emit('close')">
      <div
        v-if="show"
        :class="sizes[size]"
        class="relative z-10 inline-block w-full rounded-2xl bg-white text-left align-middle shadow-xl transition-all sm:my-8 dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
      >
        <div class="absolute top-0 right-0 hidden pt-4 pr-3 sm:block">
          <button
            type="button"
            class="rounded-full bg-white text-gray-400 transition ease-in-out hover:text-gray-500 focus:bg-gray-50 focus:ring-3 focus:ring-gray-300 focus:outline-hidden dark:bg-gray-800 dark:focus:ring-gray-500"
            @click.prevent="close"
          >
            <span class="sr-only">{{ $t('Close') }}</span>
            <X class="size-6" aria-hidden="true" />
          </button>
        </div>
        <div class="px-4 pt-5 pb-4 sm:flex sm:items-start sm:p-6">
          <div class="mt-3 w-full sm:mt-0">
            <ModalHeadline v-if="headline">{{ headline }}</ModalHeadline>
            <div>
              <slot />
            </div>
          </div>
        </div>
        <div
          v-if="!hideActions"
          class="flex flex-col gap-2 rounded-b-2xl bg-gray-100 px-4 py-3 sm:flex-row-reverse sm:px-6 dark:bg-gray-700"
        >
          <slot name="actions" :close="close">
            <AppButton
              type="button"
              :loading="actionLoading"
              :color="actionColor"
              @click.prevent="performAction"
            >
              {{ actionText }}
            </AppButton>
            <AppButton type="button" color="white" @click.prevent="close">
              {{ $t('Close') }}
            </AppButton>
          </slot>
        </div>
      </div>
    </DropIn>
  </ModalWrapper>
</template>

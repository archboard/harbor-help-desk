<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { unrefElement } from '@vueuse/core';
import { nextTick, onMounted, useTemplateRef } from 'vue';
import AppButton from '@/components/AppButton.vue';
import Modal from '@/components/modals/Modal.vue';

const { loading = false } = defineProps<{ loading?: boolean }>();

const emit = defineEmits<{
  close: [];
  confirmed: [close: () => void];
}>();

const modal = useTemplateRef<InstanceType<typeof Modal>>('modal');
const action = useTemplateRef<InstanceType<typeof AppButton>>('action');

function close(): void {
  modal.value?.close();
}

onMounted(() => {
  void nextTick(() =>
    (unrefElement(action.value) as HTMLElement | undefined)?.focus(),
  );
});
</script>

<template>
  <Modal ref="modal" size="sm" @close="emit('close')">
    <div>
      <slot name="icon">
        <div
          class="mx-auto flex size-12 items-center justify-center rounded-full bg-yellow-100"
        >
          <TriangleAlert class="size-6 text-yellow-600" />
        </div>
      </slot>

      <div class="mt-3 w-full text-center sm:mt-5">
        <h3 class="text-lg leading-6">
          <slot name="headline">{{ $t('Are you sure?') }}</slot>
        </h3>
        <div class="mt-2">
          <p class="text-sm">
            <slot>{{ $t('This action cannot be undone.') }}</slot>
          </p>
        </div>
      </div>
    </div>

    <template #actions>
      <div
        class="grid w-full gap-2 sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3"
      >
        <AppButton
          ref="action"
          type="button"
          color="yellow"
          full
          :loading="loading"
          @click.prevent="emit('confirmed', close)"
        >
          <slot name="actionText">{{ $t("I'm sure") }}</slot>
        </AppButton>
        <AppButton type="button" color="white" full @click.prevent="close">
          {{ $t('Never mind') }}
        </AppButton>
      </div>
    </template>
  </Modal>
</template>

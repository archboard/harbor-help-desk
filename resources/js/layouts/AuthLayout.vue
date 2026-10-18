<script setup lang="ts">
import logoMask from '@/components/icons/logo-mask.svg';
import LavaLamp from '@/components/LavaLamp.vue';
import Notifications from '@/components/Notifications.vue';

const { title = '', description = '' } = defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <!-- wrap-reverse puts the lava panel on top once the columns stack on phones -->
  <div class="flex min-h-screen flex-wrap-reverse">
    <div
      class="flex min-w-0 flex-[1_1_420px] flex-col justify-center px-6 py-10 sm:px-12"
    >
      <div class="mx-auto w-full max-w-sm">
        <div v-if="title" class="mb-8 space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
          <p
            v-if="description"
            class="text-sm text-gray-600 dark:text-gray-400"
          >
            {{ description }}
          </p>
        </div>

        <slot />
      </div>
    </div>

    <LavaLamp
      class="m-4 min-h-80 flex-[1_1_520px] rounded-3xl bg-primary-950"
      :colors="[
        'bg-primary-400',
        'bg-sky-300',
        'bg-primary-700',
        'bg-cyan-400',
        'bg-indigo-400',
      ]"
      :mask="logoMask"
    />

    <Notifications />
  </div>
</template>

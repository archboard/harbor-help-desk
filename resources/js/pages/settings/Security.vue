<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';

defineOptions({ layout: { title: 'Security' } });
</script>

<template>
  <Head title="Security" />

  <CardWrapper>
    <CardPadding>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Security settings are coming soon.
      </p>
    </CardPadding>
  </CardWrapper>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';

defineOptions({ layout: { title: 'Dashboard' } });
</script>

<template>
  <Head title="Dashboard" />

  <CardWrapper>
    <CardPadding>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        The ticket dashboard is coming soon.
      </p>
    </CardPadding>
  </CardWrapper>
</template>

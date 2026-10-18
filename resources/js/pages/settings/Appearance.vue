<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';

defineOptions({ layout: { title: 'Appearance' } });
</script>

<template>
  <Head title="Appearance" />

  <CardWrapper>
    <CardPadding class="space-y-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Choose how Harbor Help looks to you.
      </p>
      <AppearanceTabs />
    </CardPadding>
  </CardWrapper>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';

defineOptions({ layout: { title: 'Desks' } });
</script>

<template>
  <Head title="Desks" />

  <CardWrapper>
    <CardPadding>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Desk management is coming soon.
      </p>
    </CardPadding>
  </CardWrapper>
</template>

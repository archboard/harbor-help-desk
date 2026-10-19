<script setup lang="ts">
import Pill from '@/components/Pill.vue';
import type { TicketPriority } from '@/types';

const { priority } = defineProps<{ priority: TicketPriority; label: string }>();

const colors = {
  low: 'gray',
  normal: 'primary',
  high: 'yellow',
  urgent: 'red',
} as const;
</script>

<template>
  <Pill :color="colors[priority]">{{ label }}</Pill>
</template>

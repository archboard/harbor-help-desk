<script setup lang="ts">
import Pill from '@/components/Pill.vue';

const { priority } = defineProps<{
  priority: App.Enums.TicketPriority;
  label: string;
}>();

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

<script setup lang="ts">
import Pill from '@/components/Pill.vue';

const { status } = defineProps<{
  status: App.Enums.TicketStatus;
  label: string;
}>();

const colors = {
  open: 'blue',
  pending: 'yellow',
  on_hold: 'fuchsia',
  solved: 'green',
  closed: 'gray',
} as const;
</script>

<template>
  <Pill :color="colors[status]">{{ label }}</Pill>
</template>

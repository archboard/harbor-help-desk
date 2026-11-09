<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TicketDetail from '@/components/tickets/TicketDetail.vue';
import type { TicketPageProps } from '@/types';

defineOptions({ layout: { title: '' } });

const props = defineProps<TicketPageProps>();
</script>

<template>
  <Head :title="`#${props.ticket.number} ${props.ticket.subject}`" />

  <TicketDetail
    v-bind="props"
    :reload-only="['ticket', 'comments', 'events', 'tags', 'attachments']"
  />
</template>

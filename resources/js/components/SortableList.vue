<script setup lang="ts">
import {
  insertNodeAt,
  removeNode,
  useSortable,
} from '@vueuse/integrations/useSortable';
import { useTemplateRef } from 'vue';

/**
 * A container whose children can be dragged by `handle`. It never reorders anything itself:
 * the DOM is put back and `reorder` says where the child was dropped, so the caller changes
 * its list (or refuses) and Vue renders the result.
 */
const { tag = 'div', handle = '.drag-handle' } = defineProps<{
  tag?: string;
  handle?: string;
}>();

const emit = defineEmits<{ reorder: [from: number, to: number] }>();

const el = useTemplateRef<HTMLElement>('el');

useSortable(el, [], {
  handle,
  animation: 150,
  onUpdate: (event) => {
    removeNode(event.item);
    insertNodeAt(event.from, event.item, event.oldIndex!);
    emit('reorder', event.oldIndex!, event.newIndex!);
  },
});
</script>

<template>
  <component :is="tag" ref="el">
    <slot />
  </component>
</template>

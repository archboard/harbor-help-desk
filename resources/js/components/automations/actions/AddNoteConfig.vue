<script setup lang="ts">
import PlaceholderTextarea from '../PlaceholderTextarea.vue';
import type { ActionEditorProps } from './types';

/** The "Add a note" action: an internal note, with placeholders. */
const { errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ body?: string }>({ required: true });
</script>

<template>
  <PlaceholderTextarea
    v-model="config.body"
    :catalog="catalog"
    :trigger="trigger"
    label="Note"
    required
    :error="errors.body"
  />
</template>

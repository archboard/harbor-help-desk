<script setup lang="ts">
import PlaceholderTextarea from '../PlaceholderTextarea.vue';
import type { ActionEditorProps } from './types';

/** The "Send a reply" action: a public reply, with placeholders. */
const { errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ body?: string }>({ required: true });
</script>

<template>
  <PlaceholderTextarea
    v-model="config.body"
    :catalog="catalog"
    :trigger="trigger"
    :label="$t('Reply')"
    required
    :error="errors.body"
  />
</template>

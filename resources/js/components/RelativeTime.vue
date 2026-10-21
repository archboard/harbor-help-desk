<script lang="ts">
import { useStorage } from '@vueuse/core';

/**
 * Shared by every instance, so clicking one switches them all.
 */
const showRelative = useStorage('harbor:relative-times', true);
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime, timeAgo } from '@/lib/dates';

const { datetime } = defineProps<{
  datetime: string | null;
}>();

const relative = computed(() => timeAgo(datetime));
const absolute = computed(() => formatDateTime(datetime));

function toggle(): void {
  showRelative.value = !showRelative.value;
}
</script>

<template>
  <time
    :datetime="datetime ?? undefined"
    :title="showRelative ? absolute : relative"
    role="button"
    tabindex="0"
    class="cursor-pointer"
    @click.stop.prevent="toggle"
    @keydown.enter.stop.prevent="toggle"
    @keydown.space.stop.prevent="toggle"
  >
    {{ showRelative ? relative : absolute }}
  </time>
</template>

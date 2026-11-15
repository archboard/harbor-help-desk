<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { show } from '@/routes/desks';
import { edit as customFields } from '@/routes/desks/custom-fields';
import { index as members } from '@/routes/desks/members';

const { isCurrentUrl } = useCurrentUrl();

const page = usePage<{ desk: App.Data.Desks.DeskData }>();

const sections = computed(() => {
  const id = page.props.desk.id;

  return [
    { label: trans('General'), href: show(id).url },
    { label: trans('Members'), href: members(id).url },
    { label: trans('Custom fields'), href: customFields(id).url },
  ];
});
</script>

<template>
  <div class="md:grid md:grid-cols-[14rem_1fr] md:gap-8">
    <aside class="mb-6 md:mb-0">
      <nav
        class="flex gap-1 overflow-x-auto md:flex-col"
        :aria-label="$t('Desk settings')"
      >
        <Link
          v-for="section in sections"
          :key="section.href"
          :href="section.href"
          :aria-current="isCurrentUrl(section.href) ? 'page' : undefined"
          :class="[
            'shrink-0 rounded-md px-3 py-2 text-sm font-medium',
            isCurrentUrl(section.href)
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
              : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
          ]"
        >
          {{ section.label }}
        </Link>
      </nav>
    </aside>

    <div class="min-w-0">
      <slot />
    </div>
  </div>
</template>

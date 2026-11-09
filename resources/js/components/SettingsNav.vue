<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editApiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { index as businessHours } from '@/routes/business-hours';
import { index as slaPolicies } from '@/routes/sla-policies';
import { index as macros } from '@/routes/macros';
import { edit as editNotifications } from '@/routes/notification-preferences';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const { isCurrentUrl } = useCurrentUrl();

const tabs = computed(() => [
  { label: trans('Profile'), href: editProfile().url },
  { label: trans('Security'), href: editSecurity().url },
  { label: trans('Notifications'), href: editNotifications().url },
  { label: trans('Macros'), href: macros().url },
  { label: trans('Business hours'), href: businessHours().url },
  { label: trans('SLA policies'), href: slaPolicies().url },
  { label: trans('API tokens'), href: editApiTokens().url },
  { label: trans('Appearance'), href: editAppearance().url },
]);
</script>

<template>
  <nav class="mb-6 flex gap-1 overflow-x-auto" :aria-label="$t('Settings')">
    <Link
      v-for="tab in tabs"
      :key="tab.href"
      :href="tab.href"
      :aria-current="isCurrentUrl(tab.href) ? 'page' : undefined"
      :class="[
        'shrink-0 rounded-md px-3 py-2 text-sm font-medium',
        isCurrentUrl(tab.href)
          ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
      ]"
    >
      {{ tab.label }}
    </Link>
  </nav>
</template>

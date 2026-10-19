<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editApiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { index as customFields } from '@/routes/custom-fields';
import { index as email } from '@/routes/email';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editWorkspace } from '@/routes/workspace';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const tabs = computed(() => [
  { label: 'Profile', href: editProfile().url },
  { label: 'Security', href: editSecurity().url },
  { label: 'API tokens', href: editApiTokens().url },
  { label: 'Appearance', href: editAppearance().url },
  ...(page.props.workspace?.is_admin
    ? [
        { label: 'Workspace', href: editWorkspace().url },
        { label: 'Email', href: email().url },
        { label: 'Custom fields', href: customFields().url },
      ]
    : []),
]);
</script>

<template>
  <nav class="mb-6 flex gap-1 overflow-x-auto" aria-label="Settings">
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

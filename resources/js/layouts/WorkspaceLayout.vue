<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as automations } from '@/routes/workspace/automations';
import { edit as attachments } from '@/routes/workspace/attachments';
import { index as auditLog } from '@/routes/workspace/audit-log';
import { edit as portal } from '@/routes/workspace/portal';
import { index as customFields } from '@/routes/workspace/custom-fields';
import { index as dataPrivacy } from '@/routes/workspace/data-privacy';
import { index as email } from '@/routes/workspace/email';
import { index as webhooks } from '@/routes/workspace/webhooks';
import { edit as general } from '@/routes/workspace/general';

const { isCurrentOrParentUrl } = useCurrentUrl();

const page = usePage();

const sections = computed(() => [
  { label: 'General', href: general().url },
  { label: 'Email', href: email().url },
  { label: 'Attachments', href: attachments().url },
  { label: 'Customer portal', href: portal().url },
  // Self-hosted only: the settings in it don't exist in the cloud.
  ...(page.props.multitenant
    ? []
    : [{ label: 'Automations', href: automations().url }]),
  { label: 'Custom fields', href: customFields().url },
  { label: 'Webhooks', href: webhooks().url },
  { label: 'Data & privacy', href: dataPrivacy().url },
  { label: 'Audit log', href: auditLog().url },
]);
</script>

<template>
  <div class="md:grid md:grid-cols-[14rem_1fr] md:gap-8">
    <aside class="mb-6 md:mb-0">
      <nav
        class="flex gap-1 overflow-x-auto md:flex-col"
        aria-label="Workspace settings"
      >
        <Link
          v-for="section in sections"
          :key="section.href"
          :href="section.href"
          :aria-current="
            isCurrentOrParentUrl(section.href) ? 'page' : undefined
          "
          :class="[
            'shrink-0 rounded-md px-3 py-2 text-sm font-medium',
            isCurrentOrParentUrl(section.href)
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

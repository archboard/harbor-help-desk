<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Container from '@/components/Container.vue';
import Notifications from '@/components/Notifications.vue';
import { logout } from '@/routes/portal';

const { title = '', description = '' } = defineProps<{
  title?: string;
  description?: string;
}>();

const page = usePage();
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-black">
    <header
      class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
      <Container class="flex h-16 items-center justify-between gap-4">
        <span class="truncate font-semibold">
          {{ page.props.workspace?.name ?? page.props.name }}
        </span>

        <div
          v-if="page.props.auth.contact"
          class="flex min-w-0 items-center gap-4 text-sm"
        >
          <span class="truncate text-gray-600 dark:text-gray-400">
            {{ page.props.auth.contact.email }}
          </span>
          <Link
            :href="logout()"
            method="post"
            as="button"
            class="shrink-0 font-medium text-primary-600 hover:underline dark:text-primary-400"
          >
            {{ $t('Sign out') }}
          </Link>
        </div>
      </Container>
    </header>

    <Container class="max-w-3xl py-10">
      <div v-if="title" class="mb-8 space-y-2">
        <h1 class="font-display text-3xl tracking-tight">{{ title }}</h1>
        <p v-if="description" class="text-sm text-gray-600 dark:text-gray-400">
          {{ description }}
        </p>
      </div>

      <slot />
    </Container>

    <Notifications />
  </div>
</template>

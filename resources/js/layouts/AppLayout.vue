<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
  Menu,
  MenuButton,
} from '@headlessui/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu as MenuIcon, X } from '@lucide/vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import Container from '@/components/Container.vue';
import ArchboardLogo from '@/components/icons/ArchboardLogo.vue';
import Notifications from '@/components/Notifications.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, logout } from '@/routes';
import { edit as editApiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { index as desks } from '@/routes/desks';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const { title = '' } = defineProps<{ title?: string }>();

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const navigation = [
  { label: 'Dashboard', href: dashboard().url },
  { label: 'Desks', href: desks().url },
];

const userNavigation = [
  { label: 'Profile', href: editProfile().url },
  { label: 'Security', href: editSecurity().url },
  { label: 'API tokens', href: editApiTokens().url },
  { label: 'Appearance', href: editAppearance().url },
];
</script>

<template>
  <div class="min-h-screen">
    <Disclosure
      v-slot="{ open }"
      as="nav"
      class="sticky top-0 z-30 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950"
    >
      <Container>
        <div class="flex h-16 items-center justify-between">
          <div class="flex items-center gap-8">
            <Link :href="dashboard()" class="shrink-0">
              <span class="sr-only">{{ page.props.name }}</span>
              <ArchboardLogo
                class="size-8 text-primary-600 dark:text-primary-400"
              />
            </Link>

            <div class="hidden items-center gap-1 md:flex">
              <Link
                v-for="item in navigation"
                :key="item.href"
                :href="item.href"
                :class="[
                  'rounded-md px-3 py-2 text-sm font-medium',
                  isCurrentOrParentUrl(item.href)
                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
                ]"
              >
                {{ item.label }}
              </Link>
            </div>
          </div>

          <Menu as="div" class="relative hidden md:block">
            <Float placement="bottom-end" :offset="8" portal>
              <MenuButton
                class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:text-gray-200 dark:hover:bg-gray-800"
              >
                {{ page.props.auth.user.name }}
                <ChevronDown class="size-4" aria-hidden="true" />
              </MenuButton>

              <AppMenuItems>
                <div class="p-1">
                  <AppMenuItem
                    v-for="item in userNavigation"
                    :key="item.href"
                    :href="item.href"
                  >
                    {{ item.label }}
                  </AppMenuItem>
                </div>
                <div class="p-1">
                  <AppMenuItem :href="logout()" method="post" as="button">
                    Log out
                  </AppMenuItem>
                </div>
              </AppMenuItems>
            </Float>
          </Menu>

          <DisclosureButton
            class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 focus:ring-2 focus:ring-primary-500 focus:outline-hidden md:hidden dark:text-gray-300 dark:hover:bg-gray-800"
          >
            <span class="sr-only">Open main menu</span>
            <X v-if="open" class="size-6" aria-hidden="true" />
            <MenuIcon v-else class="size-6" aria-hidden="true" />
          </DisclosureButton>
        </div>
      </Container>

      <DisclosurePanel
        v-slot="{ close }"
        class="border-t border-gray-200 md:hidden dark:border-gray-800"
      >
        <div class="space-y-1 px-2 py-3">
          <Link
            v-for="item in navigation"
            :key="item.href"
            :href="item.href"
            @click="close()"
            :class="[
              'block rounded-md px-3 py-2 text-base font-medium',
              isCurrentOrParentUrl(item.href)
                ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800',
            ]"
          >
            {{ item.label }}
          </Link>
        </div>
        <div
          class="space-y-1 border-t border-gray-200 px-2 py-3 dark:border-gray-800"
        >
          <p class="px-3 pb-1 text-sm text-gray-500 dark:text-gray-400">
            {{ page.props.auth.user.email }}
          </p>
          <Link
            v-for="item in userNavigation"
            :key="item.href"
            :href="item.href"
            @click="close()"
            class="block rounded-md px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
          >
            {{ item.label }}
          </Link>
          <Link
            :href="logout()"
            method="post"
            @click="close()"
            as="button"
            class="block w-full rounded-md px-3 py-2 text-left text-base font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
          >
            Log out
          </Link>
        </div>
      </DisclosurePanel>
    </Disclosure>

    <main class="py-8">
      <Container>
        <h1 v-if="title" class="mb-6 text-2xl font-semibold tracking-tight">
          {{ title }}
        </h1>

        <slot />
      </Container>
    </main>

    <Notifications />
  </div>
</template>

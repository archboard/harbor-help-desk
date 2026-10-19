<script setup lang="ts">
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
  Popover,
  PopoverButton,
  PopoverPanel,
} from '@headlessui/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu as MenuIcon, X } from '@lucide/vue';
import { watch } from 'vue';
import Container from '@/components/Container.vue';
import ArchboardLogo from '@/components/icons/ArchboardLogo.vue';
import Notifications from '@/components/Notifications.vue';
import TimezonePrompt from '@/components/TimezonePrompt.vue';
import ScaleIn from '@/components/transitions/ScaleIn.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { setDisplayTimezone } from '@/lib/dates';
import { logout } from '@/routes';
import { edit as editApiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { index as desks } from '@/routes/desks';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as tickets } from '@/routes/tickets';

const { title = '' } = defineProps<{ title?: string }>();

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

watch(() => page.props.auth.user.timezone, setDisplayTimezone, {
  immediate: true,
});

const navigation = [
  { label: 'Tickets', href: tickets().url },
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
            <Link :href="tickets()" class="shrink-0">
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

          <Popover v-slot="{ open }" class="relative hidden md:block">
            <PopoverButton
              class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:text-gray-200 dark:hover:bg-gray-800"
            >
              {{ page.props.auth.user.name }}
              <ChevronDown
                class="size-4 transition-transform"
                :class="{ 'rotate-180': open }"
                aria-hidden="true"
              />
            </PopoverButton>

            <ScaleIn>
              <PopoverPanel
                v-slot="{ close }"
                class="absolute right-0 z-40 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 dark:divide-gray-700 dark:bg-gray-800 dark:ring-white/10"
              >
                <div class="p-1">
                  <Link
                    v-for="item in userNavigation"
                    :key="item.href"
                    :href="item.href"
                    class="block rounded-sm px-4 py-2 text-sm text-black hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden dark:text-gray-100 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                    @click="close()"
                  >
                    {{ item.label }}
                  </Link>
                </div>
                <div class="p-1">
                  <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="block w-full rounded-sm px-4 py-2 text-left text-sm text-black hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden dark:text-gray-100 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                    @click="close()"
                  >
                    Log out
                  </Link>
                </div>
              </PopoverPanel>
            </ScaleIn>
          </Popover>

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
        <TimezonePrompt />

        <h1 v-if="title" class="mb-6 text-2xl font-semibold tracking-tight">
          {{ title }}
        </h1>

        <slot />
      </Container>
    </main>

    <Notifications />
  </div>
</template>

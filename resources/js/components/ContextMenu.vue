<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Menu, MenuButton } from '@headlessui/vue';
import { EllipsisVertical } from '@lucide/vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import type { MenuLink } from '@/types';

const { menuItems = [], size = 'sm' } = defineProps<{
  menuItems?: MenuLink[];
  size?: 'sm' | 'base';
}>();
</script>

<template>
  <Menu as="div" class="relative inline-block text-left">
    <Float
      placement="bottom-end"
      :offset="4"
      enter="transition duration-100 ease-out"
      enter-from="scale-95 opacity-0"
      enter-to="scale-100 opacity-100"
      leave="transition duration-75 ease-in"
      leave-from="scale-100 opacity-100"
      leave-to="scale-95 opacity-0"
      portal
    >
      <MenuButton
        class="inline-flex items-center justify-center rounded-full p-1 focus:ring-2 focus:ring-primary-500 focus:outline-hidden"
      >
        <span class="sr-only">Open menu</span>
        <slot name="icon">
          <EllipsisVertical
            :class="[
              'text-black dark:text-white',
              size === 'sm' ? 'size-4' : 'size-5',
            ]"
          />
        </slot>
      </MenuButton>

      <AppMenuItems>
        <slot>
          <div class="p-1">
            <AppMenuItem
              v-for="item in menuItems"
              :key="item.href"
              :href="item.href"
            >
              {{ item.label }}
            </AppMenuItem>
          </div>
        </slot>
      </AppMenuItems>
    </Float>
  </Menu>
</template>

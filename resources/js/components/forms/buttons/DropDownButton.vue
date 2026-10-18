<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Menu, MenuButton } from '@headlessui/vue';
import { ChevronDown } from '@lucide/vue';
import type { Component } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import type { ButtonColor, ButtonSize, MenuLink } from '@/types';

const {
  menuItems = [],
  size = 'sm',
  icon = ChevronDown,
  color = 'white',
} = defineProps<{
  menuItems?: MenuLink[];
  size?: ButtonSize;
  icon?: Component;
  color?: ButtonColor;
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
      <MenuButton :as="AppButton" :size="size" :color="color">
        <slot>Actions</slot>
        <component
          :is="icon"
          :class="[size === 'base' ? 'size-5' : 'size-4', '-mr-1 ml-1']"
          aria-hidden="true"
        />
      </MenuButton>

      <AppMenuItems>
        <slot name="dropdown">
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

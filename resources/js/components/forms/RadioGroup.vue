<script setup lang="ts">
import {
  RadioGroup,
  RadioGroupDescription,
  RadioGroupLabel,
  RadioGroupOption,
} from '@headlessui/vue';

type RadioOption = {
  label: string;
  value: string | number | boolean;
  description?: string;
};

const { options } = defineProps<{ options: RadioOption[] }>();

const model = defineModel<string | number | boolean>();
</script>

<template>
  <RadioGroup v-model="model">
    <div class="-space-y-px rounded-md bg-white dark:bg-gray-900">
      <RadioGroupOption
        v-for="(option, optionIndex) in options"
        :key="option.label"
        v-slot="{ checked, active }"
        as="template"
        :value="option.value"
      >
        <div
          :class="[
            { 'rounded-t-md': optionIndex === 0 },
            { 'rounded-b-md': optionIndex === options.length - 1 },
            checked
              ? 'z-10 border-primary-200 bg-primary-50 dark:border-primary-700 dark:bg-primary-900'
              : 'border-gray-200 dark:border-gray-700',
            'relative flex cursor-pointer border p-4 focus:outline-hidden',
          ]"
        >
          <span
            :class="[
              checked
                ? 'border-transparent bg-primary-500'
                : 'border-gray-300 bg-white dark:bg-gray-900',
              {
                'ring-2 ring-primary-600 ring-offset-2 dark:ring-primary-400 dark:ring-offset-gray-900':
                  active,
              },
              'mt-0.5 flex size-4 shrink-0 cursor-pointer items-center justify-center rounded-full border',
            ]"
            aria-hidden="true"
          >
            <span class="size-1.5 rounded-full bg-white dark:bg-gray-900" />
          </span>
          <span class="ml-3 flex flex-col">
            <RadioGroupLabel
              as="span"
              :class="[
                checked
                  ? 'text-primary-900 dark:text-primary-100'
                  : 'text-gray-900 dark:text-gray-100',
                'block text-sm font-medium',
              ]"
            >
              {{ option.label }}
            </RadioGroupLabel>
            <RadioGroupDescription
              v-if="option.description"
              as="span"
              :class="[
                checked
                  ? 'text-primary-700 dark:text-primary-300'
                  : 'text-gray-500',
                'block text-sm',
              ]"
            >
              {{ option.description }}
            </RadioGroupDescription>
          </span>
        </div>
      </RadioGroupOption>
    </div>
  </RadioGroup>
</template>

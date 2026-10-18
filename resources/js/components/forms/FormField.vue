<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, useId } from 'vue';
import AppDatepicker from '@/components/forms/AppDatepicker.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FieldError from '@/components/forms/FieldError.vue';
import HelpText from '@/components/forms/HelpText.vue';
import Req from '@/components/forms/Req.vue';
import type { OptionsInput } from '@/types';

const {
  component = 'AppInput',
  id,
  type = 'text',
  error,
  placeholder = '',
  required = false,
  disabled = false,
  help,
  options = [],
} = defineProps<{
  component?: 'AppInput' | 'AppSelect' | 'AppTextarea' | 'AppDatepicker';
  id?: string;
  type?: string;
  error?: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  help?: string;
  options?: OptionsInput;
}>();

const model = defineModel<string | number | boolean | null>();

const generatedId = useId();
const fieldId = computed(() => id ?? generatedId);

const components = { AppDatepicker, AppInput, AppSelect, AppTextarea };
</script>

<template>
  <div class="space-y-1">
    <AppLabel v-if="$slots.default" :for="fieldId" :has-error="!!error">
      <slot />
      <Req v-if="required" />
    </AppLabel>

    <div class="relative">
      <slot
        name="component"
        :id="fieldId"
        :has-error="!!error"
        :local-value="model"
      >
        <component
          :is="components[component]"
          :id="fieldId"
          v-model="model"
          :has-error="!!error"
          :disabled="disabled"
          v-bind="{
            ...(component === 'AppInput' ? { type, placeholder } : {}),
            ...(component === 'AppSelect' ? { options } : {}),
          }"
        />
      </slot>

      <div
        v-if="error"
        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
      >
        <CircleAlert
          class="size-5 text-red-500 dark:text-red-400"
          aria-hidden="true"
        />
      </div>
    </div>

    <FieldError v-if="error">{{ error }}</FieldError>

    <slot name="after">
      <HelpText v-if="help">{{ help }}</HelpText>
    </slot>
  </div>
</template>

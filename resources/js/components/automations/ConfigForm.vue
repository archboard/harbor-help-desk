<script setup lang="ts">
import { computed, reactive } from 'vue';
import FormField from '@/components/forms/FormField.vue';
import {
  CheckboxField,
  CheckboxListField,
  InputField,
  NumberField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import type { OptionsInput } from '@/types';
import { isVisible, normalizeConfig, parseConfigRules } from './configRules';

/**
 * Edits a config object from the catalog's `config_rules` of a trigger or action: a field per
 * key, its input picked from the rules. Nested keys (`headers.*`) are not shown.
 */
const {
  rules,
  errors = {},
  choices = {},
  options = {},
} = defineProps<{
  rules: Record<string, string[]>;
  /** The labelled choices of the keys with a fixed set, from the catalog's `config_options`. */
  options?: Record<string, App.Data.Automations.CatalogOptionData[]>;
  /** Server errors keyed by config key. */
  errors?: Record<string, string>;
  /** Fixed choices for a key, e.g. the desks for `desk_id`. */
  choices?: Record<string, OptionsInput>;
}>();

const config = defineModel<Record<string, unknown>>({ required: true });

/** What is typed in list inputs, so a trailing comma survives until the next word. */
const typedLists = reactive<Record<string, string>>({});

const fields = computed(() =>
  parseConfigRules(rules).filter((field) =>
    isVisible(rules[field.key], config.value),
  ),
);

const errorFor = (key: string): string | undefined =>
  errors[key] ??
  Object.entries(errors).find(([name]) => name.startsWith(`${key}.`))?.[1];

function set(key: string, value: unknown): void {
  const empty = value === '' || value === null || value === undefined;
  const next = { ...config.value };

  if (!empty) {
    next[key] = value;
  } else if (rules[key].includes('present')) {
    next[key] = null;
  } else {
    delete next[key];
  }

  config.value = normalizeConfig(next, rules, options);
}

function setList(key: string, text: string | null | undefined): void {
  typedLists[key] = text ?? '';
  set(
    key,
    (text ?? '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean),
  );
}

const listText = (key: string): string =>
  typedLists[key] ?? [config.value[key] ?? []].flat().join(', ');

const text = (key: string): string | null =>
  config.value[key] === undefined || config.value[key] === null
    ? null
    : String(config.value[key]);
</script>

<template>
  <div class="space-y-4">
    <template v-for="field in fields" :key="field.key">
      <SelectField
        v-if="field.kind === 'select' || choices[field.key]"
        :model-value="text(field.key)"
        :label="field.label"
        :options="choices[field.key] ?? options[field.key] ?? field.options"
        :required="field.required"
        :null-option="field.required ? undefined : $t('Any')"
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event)"
      />
      <CheckboxField
        v-else-if="field.kind === 'checkbox'"
        :model-value="config[field.key] === true"
        :label="field.label"
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event)"
      />
      <NumberField
        v-else-if="field.kind === 'number'"
        :model-value="config[field.key] as number | null | undefined"
        :label="field.label"
        :required="field.required"
        :min="field.min"
        :max="field.max"
        :error="errorFor(field.key)"
        @update:model-value="
          set(
            field.key,
            $event === null || $event === ('' as never) ? null : Number($event),
          )
        "
      />
      <CheckboxListField
        v-else-if="field.kind === 'list' && options[field.key]"
        :model-value="(config[field.key] as string[] | undefined) ?? []"
        :label="field.label"
        :options="options[field.key]"
        :required="field.required"
        :help-text="
          field.required ? undefined : $t('Leave all unchecked to match any.')
        "
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event.length ? $event : null)"
      />
      <InputField
        v-else-if="field.kind === 'list'"
        :model-value="listText(field.key)"
        :label="field.label"
        :required="field.required"
        :help-text="$t('Separate values with commas.')"
        :error="errorFor(field.key)"
        @update:model-value="setList(field.key, $event)"
      />
      <FormField
        v-else-if="field.kind === 'time'"
        type="time"
        :model-value="text(field.key)"
        :required="field.required"
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event)"
      >
        {{ field.label }}
      </FormField>
      <TextareaField
        v-else-if="field.kind === 'textarea'"
        :model-value="text(field.key)"
        :label="field.label"
        :required="field.required"
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event)"
      />
      <InputField
        v-else
        :model-value="text(field.key)"
        :label="field.label"
        :required="field.required"
        :error="errorFor(field.key)"
        @update:model-value="set(field.key, $event)"
      />
    </template>
  </div>
</template>

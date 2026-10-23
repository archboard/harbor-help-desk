<script setup lang="ts">
import { computed } from 'vue';
import { SelectField } from '@/components/forms/fields';
import FieldValueInput from '../FieldValueInput.vue';

/** The "Set a field" action: a field the action can set, and a value typed by that field. */
const { catalog, errors = {} } = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  /** Server errors keyed by config key. */
  errors?: Record<string, string>;
}>();

const config = defineModel<Record<string, unknown>>({ required: true });

const fields = computed(() =>
  catalog.field_groups.flatMap((group) =>
    group.fields
      .filter((field) => field.settable)
      .map((field) => ({
        field,
        label:
          group.group === 'ticket'
            ? field.label
            : `${group.label}: ${field.label}`,
      })),
  ),
);

const selected = computed(
  () =>
    fields.value.find(({ field }) => field.key === config.value.field)?.field,
);

function setField(key: unknown): void {
  const field = fields.value.find((item) => item.field.key === key)?.field;

  config.value = {
    field: key,
    value: ['multi_enum', 'users'].includes(field?.type ?? '')
      ? []
      : field?.type === 'boolean'
        ? false
        : null,
  };
}
</script>

<template>
  <div class="space-y-4">
    <SelectField
      :model-value="(config.field as string | undefined) ?? null"
      label="Field"
      required
      :options="
        fields.map((item) => ({ value: item.field.key, label: item.label }))
      "
      :error="errors.field"
      @update:model-value="setField"
    />
    <FieldValueInput
      v-if="selected"
      v-model="config.value"
      :field="selected"
      label="Value"
      required
      :error="errors.value"
    />
  </div>
</template>

<script setup lang="ts">
import { SelectField } from '@/components/forms/fields';
import type { ActionEditorProps } from './types';

/** The "Set the SLA policy" action. No policy clears the ticket's own, so the desk's applies. */
const { slaPolicies, errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ sla_policy_id?: string | null }>({
  required: true,
});
</script>

<template>
  <SelectField
    :model-value="config.sla_policy_id ?? null"
    :label="$t('SLA policy')"
    :options="
      slaPolicies.map((policy) => ({ value: policy.id, label: policy.name }))
    "
    :help-text="
      $t(
        'Choose no policy to clear the ticket\'s own, so its desk\'s policy applies.',
      )
    "
    :error="errors.sla_policy_id"
    @update:model-value="config.sla_policy_id = ($event as string) || null"
  />
</template>

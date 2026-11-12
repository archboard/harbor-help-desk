<script setup lang="ts">
import { ListPlus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import { CheckboxListField } from '@/components/forms/fields';
import { index as customFieldSettings } from '@/routes/workspace/custom-fields';
import { update as syncCustomFields } from '@/routes/desks/custom-fields';

const { desk } = defineProps<{
  desk: App.Data.Desks.DeskData;
  customFields: App.Data.CustomFields.CustomFieldData[];
  can: { update: boolean };
}>();

defineOptions({ layout: { title: trans('Desk') } });

const page = usePage();

const fieldsForm = useForm({
  field_ids: (desk.custom_fields ?? []).map((field) => field.id),
} satisfies App.Data.Desks.SyncDeskCustomFieldsData);
</script>

<template>
  <Head :title="`${desk.name} - ${$t('Custom fields')}`" />

  <div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-lg font-semibold">{{ $t('Custom fields') }}</h2>
      <Link
        v-if="page.props.workspace?.is_admin && customFields.length"
        :href="customFieldSettings()"
        class="text-sm text-primary-600 hover:underline dark:text-primary-400"
        >{{ $t('Manage fields') }}</Link
      >
    </div>

    <EmptyState
      v-if="!customFields.length"
      :icon="ListPlus"
      :title="$t('No custom fields yet')"
    >
      {{
        $t(
          'Custom fields capture extra details on tickets, like an order number or plan.',
        )
      }}
      <template v-if="page.props.workspace?.is_admin" #actions>
        <AppButton :component="Link" :href="customFieldSettings()">{{
          $t('Manage fields')
        }}</AppButton>
      </template>
    </EmptyState>

    <CardWrapper v-else-if="can.update">
      <form
        @submit.prevent="
          fieldsForm.submit(syncCustomFields(desk.id), {
            preserveScroll: true,
          })
        "
      >
        <CardPadding>
          <CheckboxListField
            v-model="fieldsForm.field_ids"
            :label="$t('Fields on this desk\'s tickets')"
            :options="customFields"
            :error="fieldsForm.errors.field_ids"
          />
        </CardPadding>
        <CardAction :loading="fieldsForm.processing" />
      </form>
    </CardWrapper>
    <CardWrapper v-else>
      <CardPadding>
        <p
          v-if="desk.custom_fields?.length"
          class="text-sm text-gray-700 dark:text-gray-300"
        >
          {{ desk.custom_fields.map((field) => field.name).join(', ') }}
        </p>
        <p v-else class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('No custom fields are enabled on this desk.') }}
        </p>
      </CardPadding>
    </CardWrapper>
  </div>
</template>

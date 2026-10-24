<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { CheckboxField, RadioField } from '@/components/forms/fields';
import { update as updateSettings } from '@/routes/workspace/settings';

defineOptions({ layout: { title: 'Customer portal' } });

defineProps<{
  accessOptions: Record<string, string>;
}>();

const settings = usePage().props.workspace?.settings;

const form = useForm({
  portal_enabled: settings?.portal_enabled ?? true,
  portal_access: settings?.portal_access ?? 'anyone',
  surveys_enabled: settings?.surveys_enabled ?? false,
});
</script>

<template>
  <Head title="Customer portal" />

  <CardWrapper>
    <form
      @submit.prevent="form.submit(updateSettings(), { preserveScroll: true })"
    >
      <CardPadding>
        <AppFieldset>
          <CheckboxField
            v-model="form.portal_enabled"
            label="Enable the customer portal"
            help-text="Lets contacts sign in by email to see their tickets, reply and open new ones."
            :error="form.errors.portal_enabled"
          />
          <RadioField
            v-model="form.portal_access"
            label="Who can sign in"
            :options="accessOptions"
            :disabled="!form.portal_enabled"
            :error="form.errors.portal_access"
          />
          <CheckboxField
            v-model="form.surveys_enabled"
            label="Send satisfaction surveys"
            help-text="Asks the requester to rate their experience when a ticket is closed."
            :error="form.errors.surveys_enabled"
          />
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

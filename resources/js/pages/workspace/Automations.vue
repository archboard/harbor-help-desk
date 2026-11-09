<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { CheckboxField } from '@/components/forms/fields';
import { update as updateSettings } from '@/routes/workspace/settings';

defineOptions({ layout: { title: trans('Automations') } });

const settings = usePage().props.workspace?.settings;

const form = useForm({
  automations_allow_private_urls:
    settings?.automations_allow_private_urls ?? false,
});
</script>

<template>
  <Head :title="$t('Automations')" />

  <CardWrapper>
    <form
      @submit.prevent="form.submit(updateSettings(), { preserveScroll: true })"
    >
      <CardPadding>
        <AppFieldset>
          <CheckboxField
            v-model="form.automations_allow_private_urls"
            :label="$t('Allow webhooks to private addresses')"
            :help-text="
              $t(
                'Lets the “Send a webhook” action reach servers on your own network (localhost, 10.x, 192.168.x and similar). Leave it off unless you need it.',
              )
            "
            :error="form.errors.automations_allow_private_urls"
          />
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

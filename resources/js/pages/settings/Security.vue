<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm } from '@inertiajs/vue3';
import CardAction from '@/components/CardAction.vue';
import CardHeader from '@/components/CardHeader.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { PasswordField } from '@/components/forms/fields';
import SettingsNav from '@/components/SettingsNav.vue';
import { update } from '@/routes/user-password';

defineOptions({ layout: { title: trans('Security') } });

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

function submit(): void {
  form.submit(update(), {
    onSuccess: () => form.reset(),
    onError: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head :title="$t('Security')" />

  <SettingsNav />

  <CardWrapper>
    <form @submit.prevent="submit">
      <CardPadding>
        <CardHeader class="mb-4">{{ $t('Change password') }}</CardHeader>
        <AppFieldset>
          <PasswordField
            v-model="form.current_password"
            :label="$t('Current password')"
            :error="form.errors.current_password"
            required
          />
          <PasswordField
            v-model="form.password"
            :label="$t('New password')"
            :error="form.errors.password"
            required
          />
          <PasswordField
            v-model="form.password_confirmation"
            :label="$t('Confirm new password')"
            :error="form.errors.password_confirmation"
            required
          />
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

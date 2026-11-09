<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FormField from '@/components/forms/FormField.vue';
import { store } from '@/routes/password/confirm';

defineOptions({
  layout: {
    title: trans('Confirm password'),
    description: trans(
      'This is a secure area of the application. Please confirm your password before continuing.',
    ),
  },
});

const form = useForm({ password: '' });

function submit(): void {
  form.submit(store(), {
    onFinish: () => form.reset(),
  });
}
</script>

<template>
  <Head :title="$t('Confirm password')" />

  <form @submit.prevent="submit">
    <AppFieldset>
      <FormField :error="form.errors.password">
        {{ $t('Password') }}
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            autofocus
            required
            :has-error="hasError"
          />
        </template>
      </FormField>

      <AppButton type="submit" :loading="form.processing" full>
        {{ $t('Confirm password') }}
      </AppButton>
    </AppFieldset>
  </form>
</template>

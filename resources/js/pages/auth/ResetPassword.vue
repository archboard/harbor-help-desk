<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FormField from '@/components/forms/FormField.vue';
import { update } from '@/routes/password';

defineOptions({
  layout: {
    title: 'Reset password',
    description: 'Please enter your new password below',
  },
});

const props = defineProps<{
  token: string;
  email: string;
  passwordRules: string;
}>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

function submit(): void {
  form.submit(update(), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Reset password" />

  <form @submit.prevent="submit">
    <AppFieldset>
      <FormField :error="form.errors.email">
        Email
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.email"
            type="email"
            autocomplete="email"
            readonly
            :has-error="hasError"
          />
        </template>
      </FormField>

      <FormField :error="form.errors.password">
        Password
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            :passwordrules="passwordRules"
            autofocus
            required
            :has-error="hasError"
          />
        </template>
      </FormField>

      <FormField :error="form.errors.password_confirmation">
        Confirm password
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            :passwordrules="passwordRules"
            required
            :has-error="hasError"
          />
        </template>
      </FormField>

      <AppButton type="submit" :loading="form.processing" full>
        Reset password
      </AppButton>
    </AppFieldset>
  </form>
</template>

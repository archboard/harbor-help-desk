<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppLink from '@/components/AppLink.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FormField from '@/components/forms/FormField.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
  layout: {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
  },
});

defineProps<{
  status?: string;
}>();

const form = useForm({ email: '' });
</script>

<template>
  <Head title="Forgot password" />

  <SimpleAlert v-if="status" level="success" class="mb-6">
    {{ status }}
  </SimpleAlert>

  <form @submit.prevent="form.submit(email())">
    <AppFieldset>
      <FormField :error="form.errors.email">
        Email address
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="email@example.com"
            autofocus
            required
            :has-error="hasError"
          />
        </template>
      </FormField>

      <AppButton type="submit" :loading="form.processing" full>
        Email password reset link
      </AppButton>

      <p class="text-sm text-gray-600 dark:text-gray-400">
        Or, return to
        <AppLink :href="login()">log in</AppLink>
      </p>
    </AppFieldset>
  </form>
</template>

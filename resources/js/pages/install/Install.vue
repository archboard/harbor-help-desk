<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  EmailField,
  InputField,
  PasswordField,
} from '@/components/forms/fields';
import { store } from '@/routes/install';

defineOptions({
  layout: {
    title: 'Set up Harbor Help',
    description: 'Create the owner account to get started',
  },
});

const page = usePage();
const needsWorkspace = !page.props.multitenant;

const form = useForm({
  workspace_name: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function submit(): void {
  form.submit(store(), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Set up" />

  <form @submit.prevent="submit">
    <AppFieldset>
      <InputField
        v-if="needsWorkspace"
        v-model="form.workspace_name"
        label="Workspace name"
        :error="form.errors.workspace_name"
        required
      />
      <InputField
        v-model="form.name"
        label="Your name"
        :error="form.errors.name"
        required
      />
      <EmailField
        v-model="form.email"
        label="Email"
        :error="form.errors.email"
        required
      />
      <PasswordField
        v-model="form.password"
        label="Password"
        :error="form.errors.password"
        required
      />
      <PasswordField
        v-model="form.password_confirmation"
        label="Confirm password"
        :error="form.errors.password_confirmation"
        required
      />

      <AppButton type="submit" :loading="form.processing" full>
        Create account
      </AppButton>
    </AppFieldset>
  </form>
</template>

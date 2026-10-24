<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppLink from '@/components/AppLink.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { EmailField } from '@/components/forms/fields';
import { login } from '@/routes';
import { store } from '@/routes/portal/login';

defineOptions({
  layout: {
    title: 'Sign in to see your tickets',
    description: "Enter your email address and we'll send you a sign-in link.",
  },
});

defineProps<{ status?: string }>();

const form = useForm({ email: '' });
</script>

<template>
  <Head title="Sign in" />

  <SimpleAlert v-if="status" level="success" class="mb-6">
    {{ status }}
  </SimpleAlert>

  <form class="max-w-sm" @submit.prevent="form.submit(store())">
    <AppFieldset>
      <EmailField
        v-model="form.email"
        label="Email address"
        placeholder="email@example.com"
        :error="form.errors.email"
        required
      />

      <AppButton type="submit" :loading="form.processing" full>
        Email me a sign-in link
      </AppButton>
    </AppFieldset>
  </form>

  <p class="mt-6 text-sm">
    <AppLink :href="login()" class="font-normal">
      Are you an agent? Sign in here.
    </AppLink>
  </p>
</template>

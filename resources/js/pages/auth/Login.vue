<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppLink from '@/components/AppLink.vue';
import BorderSeparator from '@/components/BorderSeparator.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FormField from '@/components/forms/FormField.vue';
import { guessTimezone } from '@/lib/dates';
import { store } from '@/routes/login';
import { store as sendMagicLink } from '@/routes/magic-link';
import { request } from '@/routes/password';
import { login as portalLogin } from '@/routes/portal';

defineOptions({
  layout: {
    title: trans('Log in to your account'),
    description: trans('Enter your email and password below to log in'),
  },
});

defineProps<{
  status?: string;
  canResetPassword: boolean;
}>();

const page = usePage();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  // Saved on the account the first time, so dates show in the user's local time.
  timezone: guessTimezone(),
});

const magicLinkForm = useForm({ email: '' });

function submitMagicLink(): void {
  magicLinkForm.email = form.email;
  magicLinkForm.submit(sendMagicLink());
}

function submit(): void {
  form.submit(store(), {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head :title="$t('Log in')" />

  <SimpleAlert v-if="status" level="success" class="mb-6">
    {{ status }}
  </SimpleAlert>

  <form @submit.prevent="submit">
    <AppFieldset>
      <FormField :error="form.errors.email ?? magicLinkForm.errors.email">
        {{ $t('Email address') }}
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

      <FormField :error="form.errors.password">
        {{ $t('Password') }}
        <template #component="{ id, hasError }">
          <AppInput
            :id="id"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            :has-error="hasError"
          />
        </template>
      </FormField>

      <AppCheckbox v-model="form.remember">{{
        $t('Keep me logged in')
      }}</AppCheckbox>

      <AppButton type="submit" :loading="form.processing" full>
        {{ $t('Log in') }}
      </AppButton>

      <BorderSeparator background="bg-gray-50 dark:bg-black">
        {{ $t('Or') }}
      </BorderSeparator>

      <AppButton
        type="button"
        color="white"
        :loading="magicLinkForm.processing"
        full
        @click="submitMagicLink"
      >
        {{ $t('Email me a sign-in link') }}
      </AppButton>

      <p v-if="canResetPassword">
        <AppLink :href="request()" class="text-sm">
          {{ $t('Forgot your password?') }}
        </AppLink>
      </p>
    </AppFieldset>
  </form>

  <p v-if="page.props.portalEnabled" class="mt-6 text-sm">
    <AppLink :href="portalLogin()" class="font-normal">
      {{ $t("Looking for tickets you've submitted?") }}
    </AppLink>
  </p>
</template>

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import {
  Head,
  Link,
  router,
  setLayoutProps,
  useForm,
  usePage,
} from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  EmailField,
  InputField,
  PasswordField,
} from '@/components/forms/fields';
import { login, logout } from '@/routes';

const props = defineProps<{
  invitation: App.Data.Workspaces.WorkspaceInvitationData;
  roleLabel: string;
  mode: 'register' | 'login' | 'accept' | 'mismatch';
}>();

const page = usePage();
const workspaceName = page.props.workspace?.name ?? '';

setLayoutProps({
  title: trans('Join :workspace', { workspace: workspaceName }),
  description: props.invitation.invited_by
    ? trans(':inviter invited you to join as :role', {
        inviter: props.invitation.invited_by.name,
        role: props.roleLabel,
      })
    : trans('You were invited to join as :role', {
        role: props.roleLabel,
      }),
});

const form = useForm({
  name: '',
  password: '',
  password_confirmation: '',
});

// The link is signed, so the form posts back to this same URL, query string included.
function submit(): void {
  form.post(page.url, {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}

function signOut(): void {
  router.post(logout());
}
</script>

<template>
  <Head :title="$t('Join :workspace', { workspace: workspaceName })" />

  <form
    v-if="mode === 'register' || mode === 'accept'"
    @submit.prevent="submit"
  >
    <AppFieldset>
      <template v-if="mode === 'register'">
        <EmailField
          :model-value="invitation.email"
          :label="$t('Email')"
          disabled
        />
        <InputField
          v-model="form.name"
          :label="$t('Your name')"
          :error="form.errors.name"
          autofocus
          required
        />
        <PasswordField
          v-model="form.password"
          :label="$t('Password')"
          :error="form.errors.password"
          required
        />
        <PasswordField
          v-model="form.password_confirmation"
          :label="$t('Confirm password')"
          :error="form.errors.password_confirmation"
          required
        />
      </template>

      <p
        v-if="page.props.errors.email"
        class="text-sm text-red-600 dark:text-red-400"
      >
        {{ page.props.errors.email }}
      </p>

      <AppButton type="submit" :loading="form.processing" full>
        {{ $t('Accept invitation') }}
      </AppButton>
    </AppFieldset>
  </form>

  <div v-else-if="mode === 'login'" class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      {{ $t('You already have an account. Sign in to accept the invitation.') }}
    </p>
    <AppButton :component="Link" :href="login()" full>{{
      $t('Sign in to accept')
    }}</AppButton>
  </div>

  <div v-else class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      {{
        $t(
          'This invitation was sent to :email, but you are signed in as a different account. Sign out to continue.',
          { email: invitation.email },
        )
      }}
    </p>
    <AppButton type="button" full @click="signOut">
      {{ $t('Sign out') }}
    </AppButton>
  </div>
</template>

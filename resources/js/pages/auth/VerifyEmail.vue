<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import AppLink from '@/components/AppLink.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
  layout: {
    title: trans('Email verification'),
    description: trans(
      'Please verify your email address by clicking on the link we just emailed to you.',
    ),
  },
});

defineProps<{
  status?: string;
}>();

const form = useForm({});
</script>

<template>
  <Head :title="$t('Email verification')" />

  <SimpleAlert
    v-if="status === 'verification-link-sent'"
    level="success"
    class="mb-6"
  >
    {{ $t('A new verification link has been sent to your email address.') }}
  </SimpleAlert>

  <div class="space-y-6">
    <AppButton
      color="white"
      full
      :loading="form.processing"
      @click="form.submit(send())"
    >
      {{ $t('Resend verification email') }}
    </AppButton>

    <p class="text-center">
      <AppLink :href="logout()" method="post" as="button" class="text-sm">
        {{ $t('Log out') }}
      </AppLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLink from '@/components/AppLink.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { EmailField, InputField } from '@/components/forms/fields';
import SettingsNav from '@/components/SettingsNav.vue';
import { update } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({ layout: { title: 'Profile' } });

defineProps<{
  mustVerifyEmail: boolean;
  status?: string;
}>();

const page = usePage();

const form = useForm({
  name: page.props.auth.user.name,
  email: page.props.auth.user.email,
});

const resend = useForm({});
</script>

<template>
  <Head title="Profile" />

  <SettingsNav />

  <CardWrapper>
    <form @submit.prevent="form.submit(update())">
      <CardPadding>
        <AppFieldset>
          <InputField
            v-model="form.name"
            label="Name"
            :error="form.errors.name"
            required
          />
          <EmailField
            v-model="form.email"
            label="Email address"
            :error="form.errors.email"
            required
          />

          <div
            v-if="mustVerifyEmail && !page.props.auth.user.email_verified_at"
            class="text-sm text-gray-600 dark:text-gray-300"
          >
            <p>
              Your email address is unverified.
              <AppLink
                is="button"
                type="button"
                :disabled="resend.processing"
                @click="resend.submit(send())"
              >
                Resend the verification email.
              </AppLink>
            </p>
            <p
              v-if="status === 'verification-link-sent'"
              class="mt-2 font-medium text-green-600 dark:text-green-400"
            >
              A new verification link has been sent to your email address.
            </p>
          </div>
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

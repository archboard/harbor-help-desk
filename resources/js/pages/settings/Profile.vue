<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLink from '@/components/AppLink.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { EmailField, InputField, SelectField } from '@/components/forms/fields';
import SettingsNav from '@/components/SettingsNav.vue';
import { guessTimezone } from '@/lib/dates';
import { update } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({ layout: { title: trans('Profile') } });

const props = defineProps<{
  mustVerifyEmail: boolean;
  status?: string;
  timezones: string[];
  locales: string[];
}>();

const page = usePage();

const form = useForm({
  name: page.props.auth.user.name,
  email: page.props.auth.user.email,
  timezone: page.props.auth.user.timezone ?? guessTimezone(),
  locale: page.props.auth.user.locale ?? '',
});

const resend = useForm({});

const languages = props.locales.map((value) => ({
  value,
  label:
    new Intl.DisplayNames([value], { type: 'language' }).of(value) ?? value,
}));
</script>

<template>
  <Head :title="$t('Profile')" />

  <SettingsNav />

  <CardWrapper>
    <form @submit.prevent="form.submit(update())">
      <CardPadding>
        <AppFieldset>
          <InputField
            v-model="form.name"
            :label="$t('Name')"
            :error="form.errors.name"
            required
          />
          <EmailField
            v-model="form.email"
            :label="$t('Email address')"
            :error="form.errors.email"
            required
          />
          <SelectField
            v-model="form.timezone"
            :label="$t('Timezone')"
            :options="timezones"
            :error="form.errors.timezone"
            :help-text="$t('Dates and times are shown in this timezone.')"
            required
          />

          <SelectField
            v-if="locales.length > 1"
            v-model="form.locale"
            :label="$t('Language')"
            :options="languages"
            :null-option="$t('Browser default')"
            :error="form.errors.locale"
          />

          <div
            v-if="mustVerifyEmail && !page.props.auth.user.email_verified_at"
            class="text-sm text-gray-600 dark:text-gray-300"
          >
            <p>
              {{ $t('Your email address is unverified.') }}
              <AppLink
                is="button"
                type="button"
                :disabled="resend.processing"
                @click="resend.submit(send())"
                >{{ $t('Resend the verification email.') }}</AppLink
              >
            </p>
            <p
              v-if="status === 'verification-link-sent'"
              class="mt-2 font-medium text-green-600 dark:text-green-400"
            >
              {{
                $t(
                  'A new verification link has been sent to your email address.',
                )
              }}
            </p>
          </div>
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>

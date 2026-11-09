<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import Pill from '@/components/Pill.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  EmailField,
  InputField,
  PasswordField,
} from '@/components/forms/fields';
import { store } from '@/routes/install';

defineOptions({
  layout: {
    title: trans('Set up Harbor Help'),
    description: trans('Create the owner account to get started'),
  },
});

defineProps<{
  checks?: App.Data.Install.ServiceCheckData[];
}>();

const page = usePage();
const checking = ref(false);

function checkAgain(): void {
  router.reload({
    only: ['checks'],
    onStart: () => (checking.value = true),
    onFinish: () => (checking.value = false),
  });
}
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
  <Head :title="$t('Set up')" />

  <form @submit.prevent="submit">
    <AppFieldset>
      <div v-if="checks" class="space-y-2">
        <ul class="space-y-2 text-sm">
          <li v-for="check in checks" :key="check.name" class="flex gap-3">
            <Pill
              :color="check.ok ? 'green' : 'red'"
              class="shrink-0 self-start"
            >
              {{ check.name }}
            </Pill>
            <span class="text-gray-600 dark:text-gray-300">{{
              check.detail
            }}</span>
          </li>
        </ul>
        <AppButton
          type="button"
          size="xs"
          color="gray"
          :loading="checking"
          @click="checkAgain"
        >
          {{ $t('Check again') }}
        </AppButton>
      </div>

      <InputField
        v-if="needsWorkspace"
        v-model="form.workspace_name"
        :label="$t('Workspace name')"
        :error="form.errors.workspace_name"
        required
      />
      <InputField
        v-model="form.name"
        :label="$t('Your name')"
        :error="form.errors.name"
        required
      />
      <EmailField
        v-model="form.email"
        :label="$t('Email')"
        :error="form.errors.email"
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

      <AppButton type="submit" :loading="form.processing" full>
        {{ $t('Create account') }}
      </AppButton>
    </AppFieldset>
  </form>
</template>

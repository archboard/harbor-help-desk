<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, KeyRound } from '@lucide/vue';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { approve, deny } from '@/routes/passport/authorizations';

defineOptions({
  layout: { title: 'Authorize application' },
});

const { workspaces } = defineProps<{
  client: { id: string; name: string };
  workspaces: { id: string; name: string }[];
  scopes: string[];
  state: string | null;
  authToken: string;
  csrfToken: string;
  errors: Record<string, string>;
}>();

const selected = ref<string[]>(
  workspaces.length === 1 ? [workspaces[0].id] : [],
);
</script>

<template>
  <Head title="Authorize application" />

  <div class="space-y-6">
    <div
      class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300"
      >
        <KeyRound class="size-5" aria-hidden="true" />
      </div>
      <p class="text-sm text-gray-700 dark:text-gray-300">
        <span class="font-semibold text-gray-900 dark:text-white">{{
          client.name
        }}</span>
        wants to access your account.
      </p>
    </div>

    <div v-if="scopes.length" class="space-y-2">
      <h2 class="text-sm font-medium text-gray-900 dark:text-white">
        It will be able to
      </h2>
      <ul class="space-y-1.5">
        <li
          v-for="scope in scopes"
          :key="scope"
          class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400"
        >
          <Check
            class="mt-0.5 size-4 shrink-0 text-primary-600 dark:text-primary-400"
            aria-hidden="true"
          />
          {{ scope }}
        </li>
      </ul>
    </div>

    <!-- Plain forms, not Inertia: approval redirects to the client's external redirect URI. -->
    <form v-bind="approve.form()" class="space-y-6">
      <fieldset class="space-y-2">
        <legend class="text-sm font-medium text-gray-900 dark:text-white">
          Workspaces it can use
        </legend>
        <div
          class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900"
        >
          <div
            v-for="workspace in workspaces"
            :key="workspace.id"
            class="px-4 py-3"
          >
            <AppCheckbox v-model="selected" :value="workspace.id">
              {{ workspace.name }}
            </AppCheckbox>
          </div>
        </div>
        <FieldError v-if="errors.workspaces">{{
          errors.workspaces
        }}</FieldError>
      </fieldset>

      <input
        v-for="id in selected"
        :key="id"
        type="hidden"
        name="workspaces[]"
        :value="id"
      />
      <input type="hidden" name="_token" :value="csrfToken" />
      <input type="hidden" name="state" :value="state ?? ''" />
      <input type="hidden" name="client_id" :value="client.id" />
      <input type="hidden" name="auth_token" :value="authToken" />

      <AppButton type="submit" full :disabled="selected.length === 0">
        Authorize
      </AppButton>
    </form>

    <form v-bind="deny.form()" class="-mt-3">
      <input type="hidden" name="_token" :value="csrfToken" />
      <input type="hidden" name="state" :value="state ?? ''" />
      <input type="hidden" name="client_id" :value="client.id" />
      <input type="hidden" name="auth_token" :value="authToken" />
      <AppButton type="submit" color="white" full>Deny</AppButton>
    </form>
  </div>
</template>

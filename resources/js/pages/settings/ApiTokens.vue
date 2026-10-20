<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import CardAction from '@/components/CardAction.vue';
import CardHeader from '@/components/CardHeader.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { CheckboxListField, InputField } from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import SettingsNav from '@/components/SettingsNav.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatDate } from '@/lib/dates';
import { notify } from '@/lib/notifications';
import { destroy, store } from '@/routes/api-tokens';

defineOptions({ layout: { title: 'API tokens' } });

const { workspaces } = defineProps<{
  workspaces: App.Data.Workspaces.WorkspaceData[];
  tokens: App.Data.ApiTokens.ApiTokenData[];
}>();

const page = usePage();

const form = useForm({
  name: '',
  workspaces: workspaces.length === 1 ? [workspaces[0].id] : ([] as string[]),
} satisfies App.Data.ApiTokens.CreatePersonalAccessTokenData);

const revoking = ref<App.Data.ApiTokens.ApiTokenData | null>(null);
const revokeLoading = ref(false);

async function copyToken(token: string): Promise<void> {
  await navigator.clipboard.writeText(token);
  notify({ level: 'success', text: 'Token copied.' });
}

function revoke(close: () => void): void {
  router.delete(destroy(revoking.value!.id).url, {
    preserveScroll: true,
    onStart: () => (revokeLoading.value = true),
    onFinish: () => (revokeLoading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <Head title="API tokens" />

  <SettingsNav />

  <div class="space-y-6">
    <SimpleAlert v-if="page.flash.apiToken" level="success">
      <span class="block">
        Copy your new token now. It won't be shown again.
      </span>
      <span class="mt-2 flex items-center gap-2">
        <code
          class="min-w-0 flex-1 truncate rounded bg-white px-2 py-1 font-mono text-xs text-gray-900 dark:bg-gray-950 dark:text-gray-100"
        >
          {{ page.flash.apiToken }}
        </code>
        <AppButton
          type="button"
          size="xs"
          color="white"
          @click="copyToken(page.flash.apiToken)"
        >
          Copy
        </AppButton>
      </span>
    </SimpleAlert>

    <CardWrapper>
      <form
        @submit.prevent="
          form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
          })
        "
      >
        <CardPadding>
          <CardHeader class="mb-4">Create token</CardHeader>
          <AppFieldset>
            <InputField
              v-model="form.name"
              label="Name"
              :error="form.errors.name"
              required
            />
            <CheckboxListField
              v-model="form.workspaces"
              label="Workspaces"
              :options="workspaces"
              :error="form.errors.workspaces"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction>
          <AppButton type="submit" :loading="form.processing">
            Create token
          </AppButton>
        </CardAction>
      </form>
    </CardWrapper>

    <Table v-if="tokens.length">
      <Thead>
        <tr>
          <Th>Name</Th>
          <Th>Workspaces</Th>
          <Th>Created</Th>
          <Th>Expires</Th>
          <Th right><span class="sr-only">Actions</span></Th>
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="token in tokens" :key="token.id">
          <Td darker>{{ token.name }}</Td>
          <Td>{{ token.workspaces.join(', ') }}</Td>
          <Td>{{ formatDate(token.created_at) }}</Td>
          <Td>{{ formatDate(token.expires_at) }}</Td>
          <Td class="text-end">
            <AppButton
              type="button"
              size="xs"
              color="red"
              @click="revoking = token"
            >
              Revoke
            </AppButton>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      You don't have any API tokens yet.
    </p>
  </div>

  <ConfirmationModal
    v-if="revoking"
    :loading="revokeLoading"
    @close="revoking = null"
    @confirmed="revoke"
  >
    <template #headline>Revoke “{{ revoking.name }}”?</template>
    Anything using this token will lose access immediately.
    <template #actionText>Revoke token</template>
  </ConfirmationModal>
</template>

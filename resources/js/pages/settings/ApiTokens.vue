<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
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

defineOptions({ layout: { title: trans('API tokens') } });

const { workspaces, mcpUrl } = defineProps<{
  mcpUrl: string;
  workspaces: App.Data.Workspaces.WorkspaceData[];
  tokens: App.Data.ApiTokens.ApiTokenData[];
}>();

const page = usePage();

const form = useForm({
  name: '',
  workspaces: workspaces.length === 1 ? [workspaces[0].id] : ([] as string[]),
} satisfies App.Data.ApiTokens.CreatePersonalAccessTokenData);

const { copy } = useClipboard();
const copied = ref('');

const snippets = computed(() => [
  { key: 'claude', label: trans('Claude (connector URL)'), text: mcpUrl },
  {
    key: 'claude-code',
    label: 'Claude Code',
    text: `claude mcp add --transport http harbor ${mcpUrl}`,
  },
  {
    key: 'cursor',
    label: 'Cursor (.cursor/mcp.json)',
    text: JSON.stringify({ mcpServers: { harbor: { url: mcpUrl } } }, null, 2),
  },
  {
    key: 'vscode',
    label: 'VS Code (.vscode/mcp.json)',
    text: JSON.stringify(
      { servers: { harbor: { type: 'http', url: mcpUrl } } },
      null,
      2,
    ),
  },
]);

async function copySnippet(key: string, text: string): Promise<void> {
  await copy(text);
  copied.value = key;
}

const revoking = ref<App.Data.ApiTokens.ApiTokenData | null>(null);
const revokeLoading = ref(false);

async function copyToken(token: string): Promise<void> {
  await navigator.clipboard.writeText(token);
  notify({ level: 'success', text: trans('Token copied.') });
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
  <Head :title="$t('API tokens')" />

  <SettingsNav />

  <div class="space-y-6">
    <SimpleAlert v-if="page.flash.apiToken" level="success">
      <span class="block">
        {{ $t("Copy your new token now. It won't be shown again.") }}
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
          {{ $t('Copy') }}
        </AppButton>
      </span>
    </SimpleAlert>

    <CardWrapper>
      <CardPadding>
        <CardHeader class="mb-2">{{
          $t('Connect an AI assistant')
        }}</CardHeader>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
          <i18n-t
            keypath="Harbor Help has an MCP server, so AI assistants can read and update tickets as you. Most clients open a browser to sign in with OAuth. Alternatively, create a personal token below and send it as an :header header."
            tag="span"
          >
            <template #header>
              <code>Authorization: Bearer &lt;token&gt;</code>
            </template>
          </i18n-t>
        </p>
        <div class="space-y-4">
          <div v-for="item in snippets" :key="item.key" class="space-y-1">
            <div class="flex items-center justify-between">
              <span
                class="text-sm font-medium text-gray-700 dark:text-gray-300"
              >
                {{ item.label }}
              </span>
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="copySnippet(item.key, item.text)"
              >
                <Check
                  v-if="copied === item.key"
                  class="size-3"
                  aria-hidden="true"
                />
                <Copy v-else class="size-3" aria-hidden="true" />
                {{ copied === item.key ? $t('Copied') : $t('Copy') }}
              </AppButton>
            </div>
            <pre
              class="overflow-x-auto rounded-lg bg-gray-100 p-3 text-xs break-all whitespace-pre-wrap dark:bg-gray-900"
              >{{ item.text }}</pre>
          </div>
        </div>
      </CardPadding>
    </CardWrapper>

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
          <CardHeader class="mb-4">{{ $t('Create token') }}</CardHeader>
          <AppFieldset>
            <InputField
              v-model="form.name"
              :label="$t('Name')"
              :error="form.errors.name"
              required
            />
            <CheckboxListField
              v-model="form.workspaces"
              :label="$t('Workspaces')"
              :options="workspaces"
              :error="form.errors.workspaces"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction>
          <AppButton type="submit" :loading="form.processing">
            {{ $t('Create token') }}
          </AppButton>
        </CardAction>
      </form>
    </CardWrapper>

    <Table v-if="tokens.length">
      <Thead>
        <tr>
          <Th>{{ $t('Name') }}</Th>
          <Th>{{ $t('Workspaces') }}</Th>
          <Th>{{ $t('Created') }}</Th>
          <Th>{{ $t('Expires') }}</Th>
          <Th right
            ><span class="sr-only">{{ $t('Actions') }}</span></Th
          >
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
              {{ $t('Revoke') }}
            </AppButton>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      {{ $t("You don't have any API tokens yet.") }}
    </p>
  </div>

  <ConfirmationModal
    v-if="revoking"
    :loading="revokeLoading"
    @close="revoking = null"
    @confirmed="revoke"
  >
    <template #headline>{{
      $t('Revoke “:name”?', { name: revoking.name ?? '' })
    }}</template>
    {{ $t('Anything using this token will lose access immediately.') }}
    <template #actionText>{{ $t('Revoke token') }}</template>
  </ConfirmationModal>
</template>

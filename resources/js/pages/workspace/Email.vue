<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Copy, Check, X } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import {
  EmailField,
  InputField,
  NumberField,
  PasswordField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Modal from '@/components/modals/Modal.vue';
import Pill from '@/components/Pill.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatDateTime, timeAgo } from '@/lib/dates';
import { destroy, store, update } from '@/routes/workspace/email';
import { redirect as connect } from '@/routes/mailboxes/oauth';

defineOptions({ layout: { title: 'Email' } });

const { desks } = defineProps<{
  mailboxes: App.Data.Mailboxes.MailboxData[];
  desks: App.Data.Desks.DeskData[];
  oauthProviders: { google: boolean; microsoft: boolean };
}>();

const receivesBy: Record<
  App.Data.Mailboxes.MailboxData['receives_by'],
  string
> = {
  webhook: 'Forwarded',
  push: 'Push',
  watch: 'Watching',
  poll: 'Polling',
};

const encryptions = { ssl: 'SSL/TLS', starttls: 'STARTTLS', tls: 'TLS' };

const { copy, copied } = useClipboard();

/** The mailbox being edited, `'new'` while adding an IMAP/SMTP one, or null when closed. */
const editing = ref<App.Data.Mailboxes.MailboxData | 'new' | null>(null);
const deleting = ref<App.Data.Mailboxes.MailboxData | null>(null);
const deleteLoading = ref(false);

const form = useForm({
  name: '',
  address: '',
  default_desk_id: '',
  signature: '',
  routing: [] as App.Data.Mailboxes.MailboxRoutingRuleData[],
  settings: {
    imap: {
      host: '',
      port: 993,
      encryption: 'ssl',
      username: '',
      password: '',
      folder: 'INBOX',
    },
    smtp: {
      host: '',
      port: 587,
      encryption: 'starttls',
      username: '',
      password: '',
    },
  },
});

/** Errors for nested inputs come back keyed by dot path, e.g. `settings.imap.host`. */
function error(key: string): string | undefined {
  return (form.errors as Record<string, string>)[key];
}

function deskName(id: string): string {
  return desks.find((desk) => desk.id === id)?.name ?? 'Archived desk';
}

function open(mailbox: App.Data.Mailboxes.MailboxData | 'new'): void {
  form.clearErrors();
  form.reset();

  if (mailbox !== 'new') {
    form.name = mailbox.name;
    form.signature = mailbox.signature ?? '';
    form.default_desk_id = mailbox.default_desk_id;
    form.routing = mailbox.routing.map((rule) => ({ ...rule }));
  } else {
    form.default_desk_id = desks[0]?.id ?? '';
  }

  editing.value = mailbox;
}

function move(index: number, by: -1 | 1): void {
  const [rule] = form.routing.splice(index, 1);
  form.routing.splice(index + by, 0, rule);
}

function save(close: () => void): void {
  const mailbox = editing.value;

  form
    .transform(
      ({
        name,
        address,
        default_desk_id,
        signature,
        routing,
        settings,
      }):
        | Omit<App.Data.Mailboxes.CreateMailboxData, 'driver'>
        | App.Data.Mailboxes.UpdateMailboxData =>
        mailbox === 'new'
          ? { name, address, default_desk_id, settings }
          : { name, default_desk_id, signature: signature || null, routing },
    )
    .submit(
      mailbox === 'new' || mailbox === null ? store() : update(mailbox.id),
      { preserveScroll: true, onSuccess: close },
    );
}

function remove(close: () => void): void {
  router.delete(destroy(deleting.value!.id).url, {
    preserveScroll: true,
    onStart: () => (deleteLoading.value = true),
    onFinish: () => (deleteLoading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <Head title="Email" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Mail to these mailboxes becomes tickets, and replies go out through
        them.
      </p>
      <div class="flex shrink-0 flex-wrap gap-2">
        <AppButton
          v-if="oauthProviders.google"
          component="a"
          color="white"
          :href="connect('google').url"
        >
          Connect Google
        </AppButton>
        <AppButton
          v-if="oauthProviders.microsoft"
          component="a"
          color="white"
          :href="connect('microsoft').url"
        >
          Connect Microsoft
        </AppButton>
        <AppButton type="button" @click="open('new')">IMAP/SMTP</AppButton>
      </div>
    </div>

    <Table v-if="mailboxes.length">
      <Thead>
        <tr>
          <Th>Mailbox</Th>
          <Th>Default desk</Th>
          <Th>Rules</Th>
          <Th>New mail</Th>
          <Th right><span class="sr-only">Actions</span></Th>
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="mailbox in mailboxes" :key="mailbox.id">
          <Td darker>
            <div>{{ mailbox.name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
              {{ mailbox.driver_label }} · {{ mailbox.address }}
            </div>
            <div
              v-if="mailbox.driver === 'hosted' && mailbox.inbound_address"
              class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
            >
              Forward your own addresses here
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-sm font-mono text-gray-700 hover:text-gray-900 dark:text-gray-200 dark:hover:text-white"
                :aria-label="`Copy ${mailbox.inbound_address}`"
                @click="copy(mailbox.inbound_address)"
              >
                {{ mailbox.inbound_address }}
                <Check v-if="copied" class="size-3.5" aria-hidden="true" />
                <Copy v-else class="size-3.5" aria-hidden="true" />
              </button>
            </div>
            <p
              v-if="mailbox.last_error"
              class="mt-1 max-w-md text-xs whitespace-normal text-red-600 dark:text-red-400"
            >
              {{ mailbox.last_error }}
            </p>
          </Td>
          <Td>{{ deskName(mailbox.default_desk_id) }}</Td>
          <Td>{{ mailbox.routing.length || '—' }}</Td>
          <Td>
            <Pill :color="mailbox.last_error ? 'red' : 'gray'">
              {{ receivesBy[mailbox.receives_by] }}
            </Pill>
            <div
              v-if="mailbox.polled_at"
              class="mt-1 text-xs text-gray-500 dark:text-gray-400"
              :title="formatDateTime(mailbox.polled_at)"
            >
              Checked {{ timeAgo(mailbox.polled_at) }}
            </div>
          </Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="open(mailbox)"
            >
              Edit
            </AppButton>
            <AppButton
              v-if="mailbox.driver !== 'hosted'"
              type="button"
              size="xs"
              color="red"
              @click="deleting = mailbox"
            >
              Delete
            </AppButton>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      There are no mailboxes yet.
    </p>
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? 'Add IMAP/SMTP mailbox' : 'Edit mailbox'"
    :action-loading="form.processing"
    size="2xl"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="form.name"
          label="Name"
          help-text="Shown as the sender of replies."
          :error="form.errors.name"
          required
        />
        <EmailField
          v-if="editing === 'new'"
          v-model="form.address"
          label="Address"
          :error="form.errors.address"
          required
        />
        <SelectField
          v-model="form.default_desk_id"
          label="Default desk"
          :options="desks"
          :error="form.errors.default_desk_id"
          required
        />

        <template v-if="editing === 'new'">
          <template
            v-for="protocol in ['imap', 'smtp'] as const"
            :key="protocol"
          >
            <h3
              class="pt-2 text-sm font-semibold text-gray-900 dark:text-white"
            >
              {{ protocol === 'imap' ? 'Incoming (IMAP)' : 'Outgoing (SMTP)' }}
            </h3>
            <div class="grid gap-4 sm:grid-cols-3">
              <div class="sm:col-span-2">
                <InputField
                  v-model="form.settings[protocol].host"
                  label="Host"
                  :error="error(`settings.${protocol}.host`)"
                  required
                />
              </div>
              <NumberField
                v-model="form.settings[protocol].port"
                label="Port"
                :error="error(`settings.${protocol}.port`)"
                required
              />
            </div>
            <SelectField
              v-model="form.settings[protocol].encryption"
              label="Encryption"
              :options="encryptions"
              :error="error(`settings.${protocol}.encryption`)"
              required
            />
            <InputField
              v-model="form.settings[protocol].username"
              label="Username"
              :error="error(`settings.${protocol}.username`)"
              required
            />
            <PasswordField
              v-model="form.settings[protocol].password"
              label="Password"
              :error="error(`settings.${protocol}.password`)"
              required
            />
          </template>
          <InputField
            v-model="form.settings.imap.folder"
            label="Folder"
            :error="error('settings.imap.folder')"
          />
        </template>

        <template v-else>
          <TextareaField
            v-model="form.signature"
            label="Signature"
            help-text="Added below every reply sent from this mailbox."
            :error="form.errors.signature"
          />

          <div class="space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
              Routing rules
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              New mail sent to an address goes to its desk. The first matching
              rule wins; anything else goes to the default desk.
            </p>
            <div
              v-for="(rule, index) in form.routing"
              :key="index"
              class="space-y-1"
            >
              <div class="flex items-center gap-2">
                <AppInput
                  v-model="rule.address"
                  type="email"
                  placeholder="billing@example.com"
                  :aria-label="`Rule ${index + 1} address`"
                  :has-error="!!error(`routing.${index}.address`)"
                />
                <AppSelect
                  v-model="rule.desk_id"
                  :options="desks"
                  :aria-label="`Rule ${index + 1} desk`"
                  :has-error="!!error(`routing.${index}.desk_id`)"
                />
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  :disabled="index === 0"
                  aria-label="Move up"
                  @click="move(index, -1)"
                >
                  <ArrowUp class="size-4" aria-hidden="true" />
                </AppButton>
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  :disabled="index === form.routing.length - 1"
                  aria-label="Move down"
                  @click="move(index, 1)"
                >
                  <ArrowDown class="size-4" aria-hidden="true" />
                </AppButton>
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  aria-label="Remove rule"
                  @click="form.routing.splice(index, 1)"
                >
                  <X class="size-4" aria-hidden="true" />
                </AppButton>
              </div>
              <FieldError
                v-if="
                  error(`routing.${index}.address`) ??
                  error(`routing.${index}.desk_id`)
                "
              >
                {{
                  error(`routing.${index}.address`) ??
                  error(`routing.${index}.desk_id`)
                }}
              </FieldError>
            </div>
            <AppButton
              type="button"
              size="sm"
              color="white"
              @click="form.routing.push({ address: '', desk_id: '' })"
            >
              Add rule
            </AppButton>
            <FieldError v-if="form.errors.routing">
              {{ form.errors.routing }}
            </FieldError>
          </div>
        </template>
      </AppFieldset>
    </form>
  </Modal>

  <ConfirmationModal
    v-if="deleting"
    :loading="deleteLoading"
    @close="deleting = null"
    @confirmed="remove"
  >
    <template #headline>Delete “{{ deleting.name }}”?</template>
    Harbor stops receiving and sending mail through {{ deleting.address }}. Its
    tickets stay, and their replies go out through another mailbox.
    <template #actionText>Delete mailbox</template>
  </ConfirmationModal>
</template>

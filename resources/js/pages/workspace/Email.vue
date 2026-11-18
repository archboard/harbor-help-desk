<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Copy, Check, Inbox, X } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import CardAction from '@/components/CardAction.vue';
import CardHeader from '@/components/CardHeader.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import DropDownButton from '@/components/forms/buttons/DropDownButton.vue';
import EmptyState from '@/components/EmptyState.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import {
  CheckboxField,
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
import RelativeTime from '@/components/RelativeTime.vue';
import { destroy, store, test, update } from '@/routes/workspace/email';
import { update as updateSettings } from '@/routes/workspace/settings';
import { redirect as connect } from '@/routes/mailboxes/oauth';

defineOptions({ layout: { title: trans('Email') } });

const { desks, mailboxes } = defineProps<{
  mailboxes: App.Data.Mailboxes.MailboxData[];
  desks: App.Data.Desks.DeskData[];
  oauthProviders: { google: boolean; microsoft: boolean };
  microsoftHasSecret: boolean;
  /** The redirect URI to register on the Microsoft app. */
  oauthRedirectUri: string;
}>();

const receivesBy = computed<
  Record<App.Data.Mailboxes.MailboxData['receives_by'], string>
>(() => ({
  webhook: trans('By forwarding'),
  push: trans('Instantly'),
  watch: trans('Every few seconds'),
  poll: trans('Every minute'),
  never: trans('Send only'),
}));

const page = usePage();

const settingsForm = useForm({
  mail_notification_mailbox_id:
    page.props.workspace?.settings?.mail_notification_mailbox_id ?? '',
  mail_new_tickets_per_sender_per_hour:
    page.props.workspace?.settings?.mail_new_tickets_per_sender_per_hour ?? 20,
});

const microsoftForm = useForm({
  microsoft_client_id:
    page.props.workspace?.settings?.microsoft_client_id ?? '',
  microsoft_tenant:
    page.props.workspace?.settings?.microsoft_tenant ?? 'common',
  microsoft_client_secret: '',
});

/** A blank secret keeps the saved one, so it's only sent when typed. */
function saveMicrosoftApp(): void {
  microsoftForm
    .transform(({ microsoft_client_secret, ...app }) => ({
      ...app,
      microsoft_client_id: app.microsoft_client_id || null,
      ...(microsoft_client_secret ? { microsoft_client_secret } : {}),
    }))
    .submit(updateSettings(), {
      preserveScroll: true,
      onSuccess: () => microsoftForm.reset('microsoft_client_secret'),
    });
}

const notificationMailboxes = computed(() => [
  { value: '', label: trans('Default (oldest mailbox)') },
  ...mailboxes.map((mailbox) => ({
    value: mailbox.id,
    label: `${mailbox.name} (${mailbox.address})`,
  })),
]);

const encryptions = { ssl: 'SSL/TLS', starttls: 'STARTTLS', tls: 'TLS' };

const { copy, copied } = useClipboard({ legacy: true });

/** The mailbox being edited, the driver of the one being added, or null when closed. */
const editing = ref<
  App.Data.Mailboxes.MailboxData | 'imap' | 'postmark' | null
>(null);
const isNew = computed(() => typeof editing.value === 'string');
const isPostmark = computed(
  () =>
    editing.value === 'postmark' ||
    (typeof editing.value === 'object' && editing.value?.driver === 'postmark'),
);
const deleting = ref<App.Data.Mailboxes.MailboxData | null>(null);
const deleteLoading = ref(false);
const testing = ref<string | null>(null);

const form = useForm({
  name: '',
  address: '',
  default_desk_id: '',
  signature: '',
  routing: [] as App.Data.Mailboxes.MailboxRoutingRuleData[],
  server_token: '',
  receives: true,
  sends: true,
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
  return desks.find((desk) => desk.id === id)?.name ?? trans('Archived desk');
}

function open(
  mailbox: App.Data.Mailboxes.MailboxData | 'imap' | 'postmark',
): void {
  form.clearErrors();
  form.reset();

  if (typeof mailbox === 'object') {
    form.name = mailbox.name;
    form.address = mailbox.address;
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
        server_token,
        receives,
        sends,
        settings,
      }):
        | App.Data.Mailboxes.CreateMailboxData
        | App.Data.Mailboxes.UpdateMailboxData => {
        if (mailbox === 'postmark') {
          return {
            driver: mailbox,
            name,
            address,
            default_desk_id,
            settings: { server_token },
          };
        }

        if (mailbox === 'imap' || mailbox === null) {
          return {
            driver: 'imap',
            name,
            address,
            default_desk_id,
            settings: {
              imap: receives ? settings.imap : null,
              smtp: sends ? settings.smtp : null,
            },
          };
        }

        return {
          name,
          default_desk_id,
          signature: signature || null,
          routing,
          // A blank token keeps the saved one.
          ...(mailbox.driver === 'postmark'
            ? {
                address,
                ...(server_token ? { settings: { server_token } } : {}),
              }
            : {}),
        };
      },
    )
    .submit(
      typeof mailbox === 'object' && mailbox !== null
        ? update(mailbox.id)
        : store(),
      { preserveScroll: true, onSuccess: close },
    );
}

function runTest(mailbox: App.Data.Mailboxes.MailboxData): void {
  router.post(
    test(mailbox.id).url,
    {},
    {
      preserveScroll: true,
      onStart: () => (testing.value = mailbox.id),
      onFinish: () => (testing.value = null),
    },
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
  <Head :title="$t('Email')" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            'Mail to these mailboxes becomes tickets, and replies go out through them.',
          )
        }}
      </p>
      <div class="flex shrink-0 flex-wrap gap-2">
        <AppButton
          v-if="oauthProviders.google"
          component="a"
          color="white"
          :href="connect('google').url"
          >{{ $t('Connect Google') }}</AppButton
        >
        <AppButton
          v-if="oauthProviders.microsoft"
          component="a"
          color="white"
          :href="connect('microsoft').url"
          >{{ $t('Connect Microsoft 365') }}</AppButton
        >
        <DropDownButton size="base" color="primary">
          {{ $t('Add mailbox') }}
          <template #dropdown>
            <div class="p-1">
              <AppMenuItem is="button" type="button" @click="open('imap')">
                {{ $t('IMAP/SMTP') }}
              </AppMenuItem>
              <AppMenuItem is="button" type="button" @click="open('postmark')">
                {{ $t('Postmark') }}
              </AppMenuItem>
            </div>
          </template>
        </DropDownButton>
      </div>
    </div>

    <Table v-if="mailboxes.length">
      <Thead>
        <tr>
          <Th>{{ $t('Mailbox') }}</Th>
          <Th>{{ $t('Default desk') }}</Th>
          <Th>{{ $t('Routing rules') }}</Th>
          <Th>{{ $t('Receives mail') }}</Th>
          <Th right
            ><span class="sr-only">{{ $t('Actions') }}</span></Th
          >
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
              {{ $t('Forward your own addresses here')
              }}<button
                type="button"
                class="inline-flex items-center gap-1 rounded-sm font-mono text-gray-700 hover:text-gray-900 dark:text-gray-200 dark:hover:text-white"
                :aria-label="
                  $t('Copy :address', { address: mailbox.inbound_address })
                "
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
            >
              {{ $t('Last checked') }}
              <RelativeTime :datetime="mailbox.polled_at" />
            </div>
          </Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <AppButton
              v-if="
                (mailbox.driver === 'google' ||
                  mailbox.driver === 'microsoft') &&
                oauthProviders[mailbox.driver]
              "
              component="a"
              size="xs"
              color="white"
              :href="
                connect(mailbox.driver, {
                  query: { login_hint: mailbox.address },
                }).url
              "
              >{{ $t('Reconnect') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="white"
              :loading="testing === mailbox.id"
              :disabled="testing !== null"
              @click="runTest(mailbox)"
              >{{ $t('Test') }}</AppButton
            >
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="open(mailbox)"
              >{{ $t('Edit') }}</AppButton
            >
            <AppButton
              v-if="mailbox.driver !== 'hosted'"
              type="button"
              size="xs"
              color="red"
              @click="deleting = mailbox"
              >{{ $t('Delete') }}</AppButton
            >
          </Td>
        </tr>
      </Tbody>
    </Table>
    <EmptyState v-else :icon="Inbox" :title="$t('There are no mailboxes yet.')">
      {{
        $t(
          'Connect a mailbox above to turn incoming mail into tickets and send replies from it.',
        )
      }}
    </EmptyState>

    <CardWrapper>
      <form
        @submit.prevent="
          settingsForm
            .transform(
              ({
                mail_notification_mailbox_id,
                mail_new_tickets_per_sender_per_hour,
              }) => ({
                mail_notification_mailbox_id:
                  mail_notification_mailbox_id || null,
                mail_new_tickets_per_sender_per_hour,
              }),
            )
            .submit(updateSettings(), { preserveScroll: true })
        "
      >
        <CardPadding>
          <AppFieldset>
            <SelectField
              v-model="settingsForm.mail_notification_mailbox_id"
              :label="$t('Send app email (notifications, sign-in links) from')"
              :options="notificationMailboxes"
              :error="settingsForm.errors.mail_notification_mailbox_id"
            />
            <NumberField
              v-model="settingsForm.mail_new_tickets_per_sender_per_hour"
              :label="$t('New tickets per sender per hour')"
              :help-text="
                $t(
                  'Email beyond this is dropped. Replies to existing tickets don\'t count.',
                )
              "
              :error="settingsForm.errors.mail_new_tickets_per_sender_per_hour"
              :min="1"
              :max="1000"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="settingsForm.processing" />
      </form>
    </CardWrapper>

    <CardWrapper>
      <form @submit.prevent="saveMicrosoftApp">
        <CardPadding>
          <CardHeader class="mb-2">{{ $t('Microsoft 365 app') }}</CardHeader>
          <div class="mb-6 space-y-2 text-sm text-gray-500 dark:text-gray-400">
            <p>
              {{
                $t(
                  'To connect Microsoft 365 mailboxes, register an app for Harbor Help in the Microsoft Entra admin center:',
                )
              }}
            </p>
            <ol class="list-decimal space-y-1 pl-5">
              <li>
                {{
                  $t(
                    'Go to App registrations and choose New registration. Pick who can sign in, and add a Web redirect URI:',
                  )
                }}
                <span class="mt-1 flex items-center gap-2">
                  <code class="truncate text-gray-700 dark:text-gray-300">{{
                    oauthRedirectUri
                  }}</code>
                  <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    @click="copy(oauthRedirectUri)"
                  >
                    <span class="sr-only">{{ $t('Copy redirect URI') }}</span>
                    <Check v-if="copied" class="size-4" aria-hidden="true" />
                    <Copy v-else class="size-4" aria-hidden="true" />
                  </button>
                </span>
              </li>
              <li>
                {{
                  $t(
                    'Under API permissions, add the delegated Microsoft Graph permissions Mail.Read, Mail.Send and offline_access.',
                  )
                }}
              </li>
              <li>
                {{
                  $t(
                    'Under Certificates & secrets, create a client secret and copy its value.',
                  )
                }}
              </li>
              <li>
                {{
                  $t(
                    'Enter the application (client) ID, tenant and secret below, then use Connect Microsoft 365 above.',
                  )
                }}
              </li>
            </ol>
          </div>
          <AppFieldset>
            <InputField
              v-model="microsoftForm.microsoft_client_id"
              :label="$t('Application (client) ID')"
              :error="microsoftForm.errors.microsoft_client_id"
            />
            <InputField
              v-model="microsoftForm.microsoft_tenant"
              :label="$t('Tenant')"
              :help-text="
                $t(
                  'Your directory (tenant) ID or domain to allow only your organization, or common for any Microsoft account.',
                )
              "
              :error="microsoftForm.errors.microsoft_tenant"
              required
            />
            <PasswordField
              v-model="microsoftForm.microsoft_client_secret"
              :label="$t('Client secret')"
              :placeholder="
                microsoftHasSecret
                  ? $t('Saved. Enter a new one to replace it.')
                  : ''
              "
              :error="microsoftForm.errors.microsoft_client_secret"
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="microsoftForm.processing" />
      </form>
    </CardWrapper>
  </div>

  <Modal
    v-if="editing"
    :headline="
      editing === 'imap'
        ? $t('Add IMAP/SMTP mailbox')
        : editing === 'postmark'
          ? $t('Add Postmark mailbox')
          : $t('Edit :address', { address: editing.address })
    "
    :action-loading="form.processing"
    size="2xl"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <div
        v-if="isPostmark"
        class="mb-6 space-y-2 text-sm text-gray-500 dark:text-gray-400"
      >
        <p>
          {{
            $t(
              'Harbor sends replies through your Postmark server and receives mail from its inbound stream. In Postmark:',
            )
          }}
        </p>
        <ol class="list-decimal space-y-1 pl-5">
          <li>
            {{
              $t(
                'Open your server, go to API Tokens and copy a Server API token.',
              )
            }}
          </li>
          <li>
            {{
              $t(
                'Under Sender Signatures, confirm the email address or verify its domain, so Postmark will send from it.',
              )
            }}
          </li>
          <li>
            {{
              $t(
                "Open the server's Default Inbound Stream. Under Settings, set the inbound webhook URL below, check Include raw email content in JSON payload, and save.",
              )
            }}
            <span
              v-if="typeof editing === 'object' && editing.webhook_url"
              class="mt-1 flex items-center gap-2"
            >
              <code class="truncate text-gray-700 dark:text-gray-300">{{
                editing.webhook_url
              }}</code>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                @click="copy(editing.webhook_url)"
              >
                <span class="sr-only">{{ $t('Copy webhook URL') }}</span>
                <Check v-if="copied" class="size-4" aria-hidden="true" />
                <Copy v-else class="size-4" aria-hidden="true" />
              </button>
            </span>
            <span v-else class="mt-1 block">{{
              $t(
                'The URL appears here once the mailbox is added. Edit it to copy the URL.',
              )
            }}</span>
          </li>
          <li>
            {{
              $t(
                "Forward the email address to the stream's inbound address (…@inbound.postmarkapp.com), or set up inbound domain forwarding.",
              )
            }}
          </li>
        </ol>
      </div>
      <AppFieldset>
        <InputField
          v-model="form.name"
          :label="$t('Sender name')"
          :help-text="$t('The From name on replies, e.g. Acme Support.')"
          :error="form.errors.name"
          required
        />
        <EmailField
          v-if="isNew || isPostmark"
          v-model="form.address"
          :label="$t('Email address')"
          :help-text="$t('Customers write to it and replies are sent from it.')"
          :error="form.errors.address"
          required
        />
        <SelectField
          v-model="form.default_desk_id"
          :label="$t('Default desk')"
          :help-text="$t('New tickets go here unless a routing rule matches.')"
          :options="desks"
          :error="form.errors.default_desk_id"
          required
        />
        <PasswordField
          v-if="isPostmark"
          v-model="form.server_token"
          :label="$t('Server API token')"
          :placeholder="
            isNew ? '' : $t('Saved. Enter a new one to replace it.')
          "
          :error="error('settings.server_token') ?? error('settings')"
          :required="isNew"
        />

        <template v-if="editing === 'imap'">
          <template
            v-for="protocol in ['imap', 'smtp'] as const"
            :key="protocol"
          >
            <CheckboxField
              v-if="protocol === 'imap'"
              v-model="form.receives"
              :label="$t('Receive mail over IMAP')"
              :help-text="$t('New mail in this mailbox becomes tickets.')"
              :error="error('settings.imap')"
            />
            <CheckboxField
              v-else
              v-model="form.sends"
              :label="$t('Send mail over SMTP')"
              :help-text="
                $t('Replies and notifications go out from this address.')
              "
              :error="error('settings.smtp')"
            />
            <template v-if="protocol === 'imap' ? form.receives : form.sends">
              <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                  <InputField
                    v-model="form.settings[protocol].host"
                    :label="$t('Host')"
                    :error="error(`settings.${protocol}.host`)"
                    required
                  />
                </div>
                <NumberField
                  v-model="form.settings[protocol].port"
                  :label="$t('Port')"
                  :error="error(`settings.${protocol}.port`)"
                  required
                />
              </div>
              <SelectField
                v-model="form.settings[protocol].encryption"
                :label="$t('Encryption')"
                :options="encryptions"
                :error="error(`settings.${protocol}.encryption`)"
                required
              />
              <InputField
                v-model="form.settings[protocol].username"
                :label="$t('Username')"
                :help-text="$t('Usually the full email address.')"
                :error="error(`settings.${protocol}.username`)"
                :required="protocol === 'imap'"
              />
              <PasswordField
                v-model="form.settings[protocol].password"
                :label="$t('Password')"
                :help-text="
                  protocol === 'smtp'
                    ? $t(
                        'Leave blank if your server accepts mail without logging in.',
                      )
                    : undefined
                "
                :error="error(`settings.${protocol}.password`)"
                :required="protocol === 'imap'"
              />
              <InputField
                v-if="protocol === 'imap'"
                v-model="form.settings.imap.folder"
                :label="$t('Folder to check')"
                :help-text="
                  $t(
                    'Only mail arriving in this folder becomes tickets. Usually INBOX.',
                  )
                "
                :error="error('settings.imap.folder')"
              />
            </template>
          </template>
        </template>

        <template v-else-if="!isNew">
          <TextareaField
            v-model="form.signature"
            :label="$t('Signature')"
            :help-text="$t('Added below every reply sent from this mailbox.')"
            :error="form.errors.signature"
          />

          <div class="space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
              {{ $t('Routing rules') }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{
                $t(
                  'New mail sent to an address goes to its desk. The first matching rule wins; anything else goes to the default desk.',
                )
              }}
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
                  :placeholder="$t('billing@example.com')"
                  :aria-label="
                    $t('Rule :number address', { number: String(index + 1) })
                  "
                  :has-error="!!error(`routing.${index}.address`)"
                />
                <AppSelect
                  v-model="rule.desk_id"
                  :options="desks"
                  :aria-label="
                    $t('Rule :number desk', { number: String(index + 1) })
                  "
                  :has-error="!!error(`routing.${index}.desk_id`)"
                />
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  :disabled="index === 0"
                  :aria-label="$t('Move up')"
                  @click="move(index, -1)"
                >
                  <ArrowUp class="size-4" aria-hidden="true" />
                </AppButton>
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  :disabled="index === form.routing.length - 1"
                  :aria-label="$t('Move down')"
                  @click="move(index, 1)"
                >
                  <ArrowDown class="size-4" aria-hidden="true" />
                </AppButton>
                <AppButton
                  type="button"
                  size="xs"
                  color="white"
                  :aria-label="$t('Remove rule')"
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
              >{{ $t('Add rule') }}</AppButton
            >
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
    <template #headline>{{
      $t('Delete “:name”?', { name: deleting.name })
    }}</template>
    {{
      $t(
        'Harbor stops receiving and sending mail through :address. Its tickets stay, and their replies go out through another mailbox.',
        { address: deleting.address },
      )
    }}
    <template #actionText>{{ $t('Delete mailbox') }}</template>
  </ConfirmationModal>
</template>

<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { EmailField, InputField } from '@/components/forms/fields';
import Modal from '@/components/modals/Modal.vue';
import TicketTable from '@/components/tickets/TicketTable.vue';
import erase from '@/actions/App/Http/Controllers/EraseContactController';
import requestExport from '@/actions/App/Http/Controllers/RequestContactExportController';
import { index, update } from '@/routes/contacts';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: '' } });

const { contact } = defineProps<{
  contact: App.Data.Contacts.ContactData;
  canExport: boolean;
  canErase: boolean;
  tickets: Paginated<App.Data.Tickets.TicketData>;
}>();

const form = useForm({
  name: contact.name,
  email: contact.email,
} satisfies App.Data.Contacts.UpdateContactData);

const erasing = ref(false);
const eraseForm = useForm({
  confirm_email: '',
} satisfies App.Data.Contacts.EraseContactData);

function eraseContact(close: () => void): void {
  eraseForm.submit(erase(contact.id), { onSuccess: close });
}
</script>

<template>
  <Head :title="contact.name" />

  <div class="space-y-6">
    <div class="space-y-1">
      <Link
        :href="index()"
        class="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        {{ $t('← Contacts') }}
      </Link>
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold tracking-tight">
          {{ contact.name }}
        </h1>
        <Form
          v-if="canExport"
          v-bind="requestExport.form(contact.id)"
          #default="{ processing }"
        >
          <AppButton type="submit" :loading="processing">
            {{ $t('Export data') }}
          </AppButton>
        </Form>
      </div>
    </div>

    <CardWrapper>
      <form
        @submit.prevent="
          form.submit(update(contact.id), {
            preserveScroll: true,
            onSuccess: () => form.defaults(),
          })
        "
      >
        <CardPadding>
          <AppFieldset class="grid gap-4 space-y-0 sm:grid-cols-2">
            <InputField
              v-model="form.name"
              :label="$t('Name')"
              :error="form.errors.name"
              required
            />
            <EmailField
              v-model="form.email"
              :label="$t('Email')"
              :error="form.errors.email"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="form.processing" />
      </form>
    </CardWrapper>

    <section class="space-y-3" :aria-label="$t('Tickets')">
      <h2 class="text-lg font-semibold">{{ $t('Tickets') }}</h2>
      <TicketTable
        v-if="tickets.data.length"
        prop="tickets"
        :tickets="tickets.data"
        hide-requester
      />
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        {{ $t('No tickets from this contact on your desks.') }}
      </p>
    </section>

    <section
      v-if="canErase"
      class="space-y-3 rounded-lg border border-red-300 p-4 dark:border-red-900"
      :aria-label="$t('Danger zone')"
    >
      <h2 class="text-lg font-semibold text-red-700 dark:text-red-400">
        {{ $t('Danger zone') }}
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            "Erasing removes this contact's name, email, messages, attachments and rating comments. Their tickets and your replies stay. This cannot be undone.",
          )
        }}
      </p>
      <AppButton type="button" color="red" @click="erasing = true">
        {{ $t('Erase contact') }}
      </AppButton>
    </section>
  </div>

  <Modal
    v-if="erasing"
    :headline="$t('Erase this contact?')"
    :action-text="$t('Erase permanently')"
    action-color="red"
    :action-loading="eraseForm.processing"
    @close="
      erasing = false;
      eraseForm.reset().clearErrors();
    "
    @action="eraseContact"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="eraseForm.confirm_email"
          :label="$t('Type :email to confirm', { email: contact.email })"
          :error="eraseForm.errors.confirm_email"
          required
        />
      </AppFieldset>
    </form>
  </Modal>
</template>

<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { EmailField, InputField } from '@/components/forms/fields';
import TicketTable from '@/components/tickets/TicketTable.vue';
import requestExport from '@/actions/App/Http/Controllers/RequestContactExportController';
import { index, update } from '@/routes/contacts';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: '' } });

const { contact } = defineProps<{
  contact: App.Data.Contacts.ContactData;
  canExport: boolean;
  tickets: Paginated<App.Data.Tickets.TicketData>;
}>();

const form = useForm({
  name: contact.name,
  email: contact.email,
} satisfies App.Data.Contacts.UpdateContactData);
</script>

<template>
  <Head :title="contact.name" />

  <div class="space-y-6">
    <div class="space-y-1">
      <Link
        :href="index()"
        class="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        ← Contacts
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
            Export data
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
              label="Name"
              :error="form.errors.name"
              required
            />
            <EmailField
              v-model="form.email"
              label="Email"
              :error="form.errors.email"
              required
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="form.processing" />
      </form>
    </CardWrapper>

    <section class="space-y-3" aria-label="Tickets">
      <h2 class="text-lg font-semibold">Tickets</h2>
      <TicketTable
        v-if="tickets.data.length"
        prop="tickets"
        :tickets="tickets.data"
        hide-requester
      />
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        No tickets from this contact on your desks.
      </p>
    </section>
  </div>
</template>

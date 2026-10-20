<script setup lang="ts">
import { Head, InfiniteScroll, Link, router, useForm } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppInput from '@/components/forms/AppInput.vue';
import { EmailField, InputField } from '@/components/forms/fields';
import Modal from '@/components/modals/Modal.vue';
import Spinner from '@/components/Spinner.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { index, show, store } from '@/routes/contacts';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: 'Contacts' } });

const { filters } = defineProps<{
  contacts: Paginated<App.Data.Contacts.ContactData>;
  filters: { search?: string };
}>();

const search = ref(filters.search ?? '');

watchDebounced(
  search,
  (value) =>
    router.get(index.url(), value.trim() ? { search: value.trim() } : {}, {
      only: ['contacts', 'filters'],
      reset: ['contacts'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }),
  { debounce: 300 },
);

const creating = ref(false);
const form = useForm({ name: '', email: '' });

function openCreate(): void {
  form.reset();
  form.clearErrors();
  creating.value = true;
}
</script>

<template>
  <Head title="Contacts" />

  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <Search
          class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
          aria-hidden="true"
        />
        <AppInput
          v-model="search"
          type="search"
          placeholder="Search by name or email"
          aria-label="Search contacts"
          class="pl-9"
        />
      </div>
      <AppButton type="button" class="shrink-0" @click="openCreate">
        New contact
      </AppButton>
    </div>

    <InfiniteScroll v-if="contacts.data.length" data="contacts">
      <Table>
        <Thead>
          <tr>
            <Th>Name</Th>
            <Th>Email</Th>
            <Th right>Tickets</Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="contact in contacts.data" :key="contact.id">
            <Td darker>
              <Link
                :href="show(contact.id)"
                class="hover:text-primary-600 hover:underline dark:hover:text-primary-400"
              >
                {{ contact.name }}
              </Link>
            </Td>
            <Td>{{ contact.email }}</Td>
            <Td class="text-end">{{ contact.tickets_count ?? 0 }}</Td>
          </tr>
        </Tbody>
      </Table>

      <template #loading>
        <div class="flex justify-center py-4">
          <Spinner class="size-5 text-gray-400" />
        </div>
      </template>
    </InfiniteScroll>
    <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ search ? 'No contacts match your search.' : 'No contacts yet.' }}
    </p>
  </div>

  <Modal
    v-if="creating"
    headline="New contact"
    action-text="Create contact"
    :action-loading="form.processing"
    @close="creating = false"
    @action="form.submit(store())"
  >
    <form class="mt-4" @submit.prevent="form.submit(store())">
      <AppFieldset>
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
    </form>
  </Modal>
</template>

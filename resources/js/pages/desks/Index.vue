<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { InputField } from '@/components/forms/fields';
import Modal from '@/components/modals/Modal.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatDate } from '@/lib/dates';
import { restore, show, store } from '@/routes/desks';

defineOptions({ layout: { title: 'Desks' } });

defineProps<{
  desks: App.Data.Desks.DeskData[];
  archivedDesks: App.Data.Desks.DeskData[];
  canManageDesks: boolean;
}>();

const creating = ref(false);
const restoring = ref<string | null>(null);

const form = useForm({
  name: '',
  slug: '',
} satisfies App.Data.Desks.CreateDeskData);

function create(): void {
  form
    // An empty slug is left out so the server makes one from the name.
    .transform(({ name, slug }) => (slug ? { name, slug } : { name }))
    .submit(store());
}

function openCreate(): void {
  form.reset();
  form.clearErrors();
  creating.value = true;
}

function restoreDesk(desk: App.Data.Desks.DeskData): void {
  router.post(
    restore(desk.id).url,
    {},
    {
      preserveScroll: true,
      onStart: () => (restoring.value = desk.id),
      onFinish: () => (restoring.value = null),
    },
  );
}
</script>

<template>
  <Head title="Desks" />

  <div class="space-y-8">
    <div class="space-y-4">
      <div v-if="canManageDesks" class="flex justify-end">
        <AppButton type="button" @click="openCreate">New desk</AppButton>
      </div>

      <Table v-if="desks.length">
        <Thead>
          <tr>
            <Th>Name</Th>
            <Th>Slug</Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="desk in desks" :key="desk.id">
            <Td darker>
              <Link
                :href="show(desk.id)"
                class="text-primary-600 hover:underline dark:text-primary-400"
              >
                {{ desk.name }}
              </Link>
            </Td>
            <Td>{{ desk.slug }}</Td>
          </tr>
        </Tbody>
      </Table>
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        You aren't on any desks yet.
      </p>
    </div>

    <div v-if="archivedDesks.length" class="space-y-4">
      <h2 class="text-lg font-semibold">Archived desks</h2>
      <Table>
        <Thead>
          <tr>
            <Th>Name</Th>
            <Th>Archived</Th>
            <Th right><span class="sr-only">Actions</span></Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="desk in archivedDesks" :key="desk.id">
            <Td darker>{{ desk.name }}</Td>
            <Td>
              {{ formatDate(desk.archived_at) }}
            </Td>
            <Td class="text-end">
              <AppButton
                type="button"
                size="xs"
                color="white"
                :loading="restoring === desk.id"
                @click="restoreDesk(desk)"
              >
                Restore
              </AppButton>
            </Td>
          </tr>
        </Tbody>
      </Table>
    </div>
  </div>

  <Modal
    v-if="creating"
    headline="New desk"
    action-text="Create desk"
    :action-loading="form.processing"
    @close="creating = false"
    @action="create"
  >
    <form class="mt-4" @submit.prevent="create">
      <AppFieldset>
        <InputField
          v-model="form.name"
          label="Name"
          :error="form.errors.name"
          required
        />
        <InputField
          v-model="form.slug"
          label="Slug"
          help-text="Leave blank to make one from the name."
          :error="form.errors.slug"
        />
      </AppFieldset>
    </form>
  </Modal>
</template>

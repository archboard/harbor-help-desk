<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  CheckboxField,
  InputField,
  NumberField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Modal from '@/components/modals/Modal.vue';
import Pill from '@/components/Pill.vue';
import SettingsNav from '@/components/SettingsNav.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { destroy, store, update } from '@/routes/custom-fields';
import type { CustomField, CustomFieldType } from '@/types';

defineOptions({ layout: { title: 'Custom fields' } });

const { fields } = defineProps<{
  fields: CustomField[];
  types: Record<CustomFieldType, string>;
}>();

/** The field being edited, `'new'` while creating one, or null when the modal is closed. */
const editing = ref<CustomField | 'new' | null>(null);
const deleting = ref<CustomField | null>(null);
const deleteLoading = ref(false);

const form = useForm({
  name: '',
  type: 'text' as CustomFieldType,
  options: '',
  is_required: false,
  position: 0 as number | null,
});

const hasOptions = computed(
  () => form.type === 'select' || form.type === 'multi_select',
);

/** Item errors come back as `options.N`, e.g. for a duplicate line. */
const optionsError = computed(
  () =>
    form.errors.options ??
    Object.entries(form.errors).find(([key]) =>
      key.startsWith('options.'),
    )?.[1],
);

function open(field: CustomField | 'new'): void {
  form.clearErrors();
  form.defaults({
    name: field === 'new' ? '' : field.name,
    type: field === 'new' ? 'text' : field.type,
    options: field === 'new' ? '' : (field.options ?? []).join('\n'),
    is_required: field === 'new' ? false : field.is_required,
    position: field === 'new' ? fields.length : field.position,
  });
  form.reset();
  editing.value = field;
}

function save(close: () => void): void {
  const field = editing.value;

  form
    .transform(({ type, options, ...data }) => ({
      ...data,
      // The type can't change once a field exists.
      ...(field === 'new' ? { type } : {}),
      ...(hasOptions.value
        ? {
            options: options
              .split('\n')
              .map((option) => option.trim())
              .filter(Boolean),
          }
        : {}),
    }))
    .submit(field === 'new' || field === null ? store() : update(field.id), {
      preserveScroll: true,
      onSuccess: close,
    });
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
  <Head title="Custom fields" />

  <SettingsNav />

  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Define fields here, then enable them on each desk.
      </p>
      <AppButton type="button" class="shrink-0" @click="open('new')">
        New field
      </AppButton>
    </div>

    <Table v-if="fields.length">
      <Thead>
        <tr>
          <Th>Name</Th>
          <Th>Type</Th>
          <Th>Required</Th>
          <Th>Position</Th>
          <Th>Desks</Th>
          <Th right><span class="sr-only">Actions</span></Th>
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="field in fields" :key="field.id">
          <Td darker>{{ field.name }}</Td>
          <Td>{{ field.type_label }}</Td>
          <Td>
            <Pill v-if="field.is_required" color="primary">Required</Pill>
            <span v-else class="text-gray-400">—</span>
          </Td>
          <Td>{{ field.position }}</Td>
          <Td>
            <span v-if="field.desks?.length">
              {{ field.desks.map((desk) => desk.name).join(', ') }}
            </span>
            <span v-else class="text-gray-400">None</span>
          </Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="open(field)"
            >
              Edit
            </AppButton>
            <AppButton
              type="button"
              size="xs"
              color="red"
              @click="deleting = field"
            >
              Delete
            </AppButton>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      There are no custom fields yet.
    </p>
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? 'New custom field' : 'Edit custom field'"
    :action-loading="form.processing"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="form.name"
          label="Name"
          :error="form.errors.name"
          required
        />
        <SelectField
          v-model="form.type"
          label="Type"
          :options="types"
          :error="form.errors.type"
          :disabled="editing !== 'new'"
          :help-text="
            editing !== 'new'
              ? 'The type can’t be changed after a field is created.'
              : undefined
          "
          required
        />
        <TextareaField
          v-if="hasOptions"
          v-model="form.options"
          label="Options"
          help-text="One per line."
          :error="optionsError"
          required
        />
        <NumberField
          v-model="form.position"
          label="Position"
          :error="form.errors.position"
        />
        <CheckboxField
          v-model="form.is_required"
          label="Required on tickets"
          :error="form.errors.is_required"
        />
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
    Its values are removed from every ticket. Ticket history keeps what they
    were.
    <template #actionText>Delete field</template>
  </ConfirmationModal>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FieldError from '@/components/forms/FieldError.vue';
import {
  DatePickerField,
  EmailField,
  InputField,
  SelectField,
} from '@/components/forms/fields';
import FilePicker from '@/components/forms/FilePicker.vue';
import FormField from '@/components/forms/FormField.vue';
import ModelCombobox from '@/components/forms/ModelCombobox.vue';
import AssigneePicker from '@/components/tickets/AssigneePicker.vue';
import CustomFieldInput from '@/components/tickets/CustomFieldInput.vue';
import { fromLocalDateTime } from '@/lib/dates';
import { firstListError } from '@/lib/files';
import { search as searchContacts } from '@/routes/contacts';
import { store } from '@/routes/desks/tickets';
import { index } from '@/routes/tickets';
import type { Contact, CustomFieldValue } from '@/types';

defineOptions({ layout: { title: 'New ticket' } });

const { desks } = defineProps<{
  desks: App.Data.Desks.DeskData[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

/** Multi-selects need a list and checkboxes a boolean even before they're set. */
function emptyValues(
  fields: App.Data.CustomFields.CustomFieldData[],
): Record<string, CustomFieldValue> {
  return Object.fromEntries(
    fields.map((field) => [
      field.id,
      field.type === 'multi_select'
        ? []
        : field.type === 'checkbox'
          ? false
          : null,
    ]),
  );
}

const form = useForm({
  desk_id: desks[0]?.id ?? '',
  subject: '',
  requester_id: null as string | null,
  requester: { name: '', email: '' },
  status: 'open',
  priority: 'normal',
  due_at: null as string | null,
  assignee_ids: [] as string[],
  custom_fields: emptyValues(desks[0]?.custom_fields ?? []),
  body: '',
  attachments: [] as File[],
});

const desk = computed(() => desks.find((option) => option.id === form.desk_id));
const fields = computed(() => desk.value?.custom_fields ?? []);
const members = computed(() => desk.value?.members ?? []);

// Each desk has its own fields and members, so start those over when the desk changes.
watch(
  () => form.desk_id,
  () => {
    form.custom_fields = emptyValues(fields.value);
    form.assignee_ids = form.assignee_ids.filter((id) =>
      members.value.some((member) => member.id === id),
    );
  },
);

/** Pick an existing contact, or type a new one's name and email. */
const newRequester = ref(false);

const errors = computed(() => form.errors as Record<string, string>);

function submit(): void {
  form
    .transform(
      ({
        desk_id,
        requester,
        requester_id,
        due_at,
        custom_fields,
        ...data
      }) => ({
        ...data,
        ...(newRequester.value ? { requester } : { requester_id }),
        due_at: fromLocalDateTime(due_at),
        // Desks without fields refuse `custom_fields` entirely.
        ...(fields.value.length ? { custom_fields } : {}),
      }),
    )
    .submit(store(form.desk_id));
}
</script>

<template>
  <Head title="New ticket" />

  <p v-if="!desks.length" class="text-sm text-gray-500 dark:text-gray-400">
    You need to be on a desk to open tickets.
  </p>

  <form v-else class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
    <CardWrapper class="lg:col-span-2">
      <CardPadding>
        <AppFieldset>
          <InputField
            v-model="form.subject"
            label="Subject"
            :error="form.errors.subject"
            required
          />

          <div v-if="newRequester" class="space-y-3">
            <div class="grid gap-4 sm:grid-cols-2">
              <InputField
                v-model="form.requester.name"
                label="Requester name"
                :error="errors['requester.name']"
                required
              />
              <EmailField
                v-model="form.requester.email"
                label="Requester email"
                :error="errors['requester.email']"
                required
              />
            </div>
            <button
              type="button"
              class="text-sm text-primary-600 hover:underline dark:text-primary-400"
              @click="newRequester = false"
            >
              Choose an existing contact instead
            </button>
          </div>
          <div v-else class="space-y-1">
            <FormField :error="errors.requester_id" required>
              Requester
              <template #component="{ id, hasError }">
                <ModelCombobox
                  :id="id"
                  v-model="form.requester_id"
                  :url="searchContacts().url"
                  :has-error="hasError"
                  :display-value="
                    (contact: Contact | null) =>
                      contact ? `${contact.name} <${contact.email}>` : ''
                  "
                />
              </template>
            </FormField>
            <button
              type="button"
              class="text-sm text-primary-600 hover:underline dark:text-primary-400"
              @click="newRequester = true"
            >
              New contact
            </button>
          </div>

          <div class="space-y-1">
            <AppLabel for="ticket-body">First message</AppLabel>
            <AppTextarea
              id="ticket-body"
              v-model="form.body"
              placeholder="What's the request? Optional."
              :has-error="!!form.errors.body"
              class="min-h-32"
            />
            <FieldError v-if="form.errors.body">
              {{ form.errors.body }}
            </FieldError>
          </div>
          <div class="space-y-1">
            <FilePicker v-model="form.attachments" />
            <FieldError v-if="firstListError(errors, 'attachments')">
              {{ firstListError(errors, 'attachments') }}
            </FieldError>
          </div>
        </AppFieldset>
      </CardPadding>
    </CardWrapper>

    <div class="space-y-6">
      <CardWrapper>
        <CardPadding>
          <AppFieldset>
            <SelectField
              v-model="form.desk_id"
              label="Desk"
              :options="desks"
              :error="form.errors.desk_id"
              required
            />
            <SelectField
              v-model="form.status"
              label="Status"
              :options="statuses"
              :error="form.errors.status"
            />
            <SelectField
              v-model="form.priority"
              label="Priority"
              :options="priorities"
              :error="form.errors.priority"
            />
            <DatePickerField
              v-model="form.due_at"
              mode="datetime"
              label="Due"
              :error="form.errors.due_at"
            />
            <div class="space-y-1">
              <AppLabel>Assignees</AppLabel>
              <AssigneePicker v-model="form.assignee_ids" :members="members" />
              <FieldError v-if="errors.assignee_ids ?? errors.user_ids">
                {{ errors.assignee_ids ?? errors.user_ids }}
              </FieldError>
            </div>
            <CustomFieldInput
              v-for="field in fields"
              :key="`${form.desk_id}-${field.id}`"
              v-model="form.custom_fields[field.id]"
              :field="field"
              :error="errors[`custom_fields.${field.id}`]"
            />
          </AppFieldset>
        </CardPadding>
      </CardWrapper>

      <div class="flex justify-end gap-2">
        <AppButton :component="Link" :href="index()" color="white">
          Cancel
        </AppButton>
        <AppButton type="submit" :loading="form.processing">
          Create ticket
        </AppButton>
      </div>
    </div>
  </form>
</template>

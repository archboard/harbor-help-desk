<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardHeader from '@/components/CardHeader.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import {
  CheckboxListField,
  InputField,
  ObjectComboboxField,
  SelectField,
} from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { notify } from '@/lib/notifications';
import { index as customFieldSettings } from '@/routes/workspace/custom-fields';
import { destroy, update } from '@/routes/desks';
import { update as syncCustomFields } from '@/routes/desks/custom-fields';
import {
  destroy as removeMember,
  update as setMember,
} from '@/routes/desks/members';

const { desk } = defineProps<{
  desk: App.Data.Desks.DeskData;
  members: App.Data.Desks.DeskMemberData[];
  candidates: App.Data.Users.UserData[];
  roles: Record<App.Enums.DeskRole, string>;
  strategies: Record<App.Enums.AssignmentStrategy, string>;
  customFields: App.Data.CustomFields.CustomFieldData[];
  can: { update: boolean; manageMembers: boolean; archive: boolean };
}>();

defineOptions({ layout: { title: 'Desk' } });

const page = usePage();

const form = useForm({
  name: desk.name,
  slug: desk.slug,
  assignment_strategy: desk.assignment_strategy,
} satisfies App.Data.Desks.UpdateDeskData);

const fieldsForm = useForm({
  field_ids: (desk.custom_fields ?? []).map((field) => field.id),
} satisfies App.Data.Desks.SyncDeskCustomFieldsData);

const addForm = useForm({
  user_id: null as string | null,
  role: 'agent' as App.Enums.DeskRole,
});

/** Bumped after adding someone, to clear the combobox's selection. */
const addFormKey = ref(0);

const removing = ref<App.Data.Desks.DeskMemberData | null>(null);
const removeLoading = ref(false);
const archiving = ref(false);
const archiveLoading = ref(false);

function addMember(): void {
  if (!addForm.user_id) {
    addForm.setError('user_id', 'Choose someone to add.');

    return;
  }

  addForm
    .transform(({ role }): App.Data.Desks.SetDeskMemberData => ({ role }))
    .submit(setMember({ desk: desk.id, user: addForm.user_id }), {
      preserveScroll: true,
      onSuccess: () => {
        addForm.reset();
        addFormKey.value++;
      },
    });
}

function changeRole(
  member: App.Data.Desks.DeskMemberData,
  role: App.Enums.DeskRole,
): void {
  router.put(
    setMember({ desk: desk.id, user: member.id }).url,
    { role },
    { preserveScroll: true },
  );
}

function setInRotation(
  member: App.Data.Desks.DeskMemberData,
  inRotation: boolean,
): void {
  router.put(
    setMember({ desk: desk.id, user: member.id }).url,
    { role: member.role, in_rotation: inRotation },
    { preserveScroll: true },
  );
}

function remove(close: () => void): void {
  router.delete(removeMember({ desk: desk.id, user: removing.value!.id }).url, {
    preserveScroll: true,
    onStart: () => (removeLoading.value = true),
    onFinish: () => (removeLoading.value = false),
    onSuccess: close,
  });
}

function archive(close: () => void): void {
  router.delete(destroy(desk.id).url, {
    onStart: () => (archiveLoading.value = true),
    onFinish: () => (archiveLoading.value = false),
    onError: (errors) => {
      close();
      notify({ level: 'error', text: errors.desk ?? 'Unable to archive.' });
    },
  });
}
</script>

<template>
  <Head :title="desk.name" />

  <div class="space-y-6">
    <CardWrapper v-if="can.update">
      <form
        @submit.prevent="form.submit(update(desk.id), { preserveScroll: true })"
      >
        <CardPadding>
          <CardHeader class="mb-4">{{ desk.name }}</CardHeader>
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
              :error="form.errors.slug"
              required
            />
            <SelectField
              v-model="form.assignment_strategy"
              label="Automatic assignment"
              :options="strategies"
              hide-null
              :error="form.errors.assignment_strategy"
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="form.processing" />
      </form>
    </CardWrapper>
    <CardWrapper v-else>
      <CardPadding>
        <CardHeader>{{ desk.name }}</CardHeader>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ desk.slug }}
        </p>
      </CardPadding>
    </CardWrapper>

    <div class="space-y-4">
      <h2 class="text-lg font-semibold">Members</h2>

      <Table v-if="members.length">
        <Thead>
          <tr>
            <Th>Name</Th>
            <Th>Email</Th>
            <Th>Role</Th>
            <Th>In rotation</Th>
            <Th v-if="can.manageMembers" right>
              <span class="sr-only">Actions</span>
            </Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="member in members" :key="member.id">
            <Td darker>{{ member.name }}</Td>
            <Td>{{ member.email }}</Td>
            <Td>
              <AppSelect
                v-if="can.manageMembers && member.role"
                :model-value="member.role"
                :options="roles"
                hide-null
                :aria-label="`Role for ${member.name}`"
                class="max-w-40"
                @update:model-value="
                  changeRole(member, $event as App.Enums.DeskRole)
                "
              />
              <template v-else>
                {{ member.role ? roles[member.role] : '—' }}
              </template>
            </Td>
            <Td>
              <AppCheckbox
                v-if="can.manageMembers"
                :model-value="member.in_rotation"
                :aria-label="`In rotation: ${member.name}`"
                @update:model-value="setInRotation(member, Boolean($event))"
              />
              <template v-else>{{
                member.in_rotation ? 'Yes' : 'No'
              }}</template>
            </Td>
            <Td v-if="can.manageMembers" class="text-end">
              <AppButton
                type="button"
                size="xs"
                color="red"
                @click="removing = member"
              >
                Remove
              </AppButton>
            </Td>
          </tr>
        </Tbody>
      </Table>
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        Nobody is on this desk yet.
      </p>

      <CardWrapper v-if="can.manageMembers && candidates.length">
        <form @submit.prevent="addMember">
          <CardPadding
            class="grid items-start gap-4 sm:grid-cols-[1fr_12rem_auto]"
          >
            <ObjectComboboxField
              :key="addFormKey"
              v-model="addForm.user_id"
              label="Add a member"
              :options="candidates"
              :error="addForm.errors.user_id"
            />
            <SelectField
              v-model="addForm.role"
              label="Role"
              :options="roles"
              :error="addForm.errors.role"
            />
            <AppButton
              type="submit"
              class="sm:mt-6"
              :loading="addForm.processing"
            >
              Add
            </AppButton>
          </CardPadding>
        </form>
      </CardWrapper>
    </div>

    <div class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold">Custom fields</h2>
        <Link
          v-if="page.props.workspace?.is_admin"
          :href="customFieldSettings()"
          class="text-sm text-primary-600 hover:underline dark:text-primary-400"
        >
          Manage fields
        </Link>
      </div>

      <CardWrapper v-if="can.update && customFields.length">
        <form
          @submit.prevent="
            fieldsForm.submit(syncCustomFields(desk.id), {
              preserveScroll: true,
            })
          "
        >
          <CardPadding>
            <CheckboxListField
              v-model="fieldsForm.field_ids"
              label="Fields on this desk's tickets"
              :options="customFields"
              :error="fieldsForm.errors.field_ids"
            />
          </CardPadding>
          <CardAction :loading="fieldsForm.processing" />
        </form>
      </CardWrapper>
      <p
        v-else-if="desk.custom_fields?.length"
        class="text-sm text-gray-700 dark:text-gray-300"
      >
        {{ desk.custom_fields.map((field) => field.name).join(', ') }}
      </p>
      <p v-else class="text-sm text-gray-500 dark:text-gray-400">
        {{
          customFields.length
            ? 'No custom fields are enabled on this desk.'
            : 'The workspace has no custom fields yet.'
        }}
      </p>
    </div>

    <div v-if="can.archive" class="flex justify-end">
      <AppButton type="button" color="red" @click="archiving = true">
        Archive desk
      </AppButton>
    </div>
  </div>

  <ConfirmationModal
    v-if="removing"
    :loading="removeLoading"
    @close="removing = null"
    @confirmed="remove"
  >
    <template #headline>Remove {{ removing.name }}?</template>
    They'll lose access to this desk's tickets.
    <template #actionText>Remove</template>
  </ConfirmationModal>

  <ConfirmationModal
    v-if="archiving"
    :loading="archiveLoading"
    @close="archiving = false"
    @confirmed="archive"
  >
    <template #headline>Archive {{ desk.name }}?</template>
    Its tickets stay as they are. An admin can restore the desk later.
    <template #actionText>Archive desk</template>
  </ConfirmationModal>
</template>

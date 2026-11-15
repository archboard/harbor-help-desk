<script setup lang="ts">
import { Users } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import { ObjectComboboxField, SelectField } from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import {
  destroy as removeMember,
  update as setMember,
} from '@/routes/desks/members';

const { desk } = defineProps<{
  desk: App.Data.Desks.DeskData;
  members: App.Data.Desks.DeskMemberData[];
  candidates: App.Data.Users.UserData[];
  roles: Record<App.Enums.DeskRole, string>;
  can: { manageMembers: boolean };
}>();

defineOptions({ layout: { title: trans('Desk') } });

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
    addForm.setError('user_id', trans('Choose someone to add.'));

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
</script>

<template>
  <Head :title="`${desk.name} - ${$t('Members')}`" />

  <div class="space-y-4">
    <Table v-if="members.length">
      <Thead>
        <tr>
          <Th>{{ $t('Name') }}</Th>
          <Th>{{ $t('Email') }}</Th>
          <Th>{{ $t('Role') }}</Th>
          <Th>{{ $t('In rotation') }}</Th>
          <Th v-if="can.manageMembers" right>
            <span class="sr-only">{{ $t('Actions') }}</span>
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
              :aria-label="$t('Role for :name', { name: member.name })"
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
              :aria-label="$t('In rotation: :name', { name: member.name })"
              @update:model-value="setInRotation(member, Boolean($event))"
            />
            <template v-else>{{
              member.in_rotation ? $t('Yes') : $t('No')
            }}</template>
          </Td>
          <Td v-if="can.manageMembers" class="text-end">
            <AppButton
              type="button"
              size="xs"
              color="red"
              @click="removing = member"
              >{{ $t('Remove') }}</AppButton
            >
          </Td>
        </tr>
      </Tbody>
    </Table>
    <EmptyState
      v-else
      :icon="Users"
      :title="$t('Nobody is on this desk yet.')"
    />

    <CardWrapper v-if="can.manageMembers && candidates.length">
      <form @submit.prevent="addMember">
        <CardPadding
          class="grid items-start gap-4 sm:grid-cols-[1fr_12rem_auto]"
        >
          <ObjectComboboxField
            :key="addFormKey"
            v-model="addForm.user_id"
            :label="$t('Add a member')"
            :options="candidates"
            :error="addForm.errors.user_id"
          />
          <SelectField
            v-model="addForm.role"
            :label="$t('Role')"
            :options="roles"
            :error="addForm.errors.role"
          />
          <AppButton
            type="submit"
            class="sm:mt-6"
            :loading="addForm.processing"
            >{{ $t('Add') }}</AppButton
          >
        </CardPadding>
      </form>
    </CardWrapper>
  </div>

  <ConfirmationModal
    v-if="removing"
    :loading="removeLoading"
    @close="removing = null"
    @confirmed="remove"
  >
    <template #headline>{{
      $t('Remove :name?', { name: removing.name })
    }}</template
    >{{ $t("They'll lose access to this desk's tickets.")
    }}<template #actionText>{{ $t('Remove') }}</template>
  </ConfirmationModal>
</template>

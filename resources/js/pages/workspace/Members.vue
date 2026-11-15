<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import { InputField, SelectField } from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatDate } from '@/lib/dates';
import {
  destroy as revokeInvitation,
  resend as resendInvitation,
  store as inviteMember,
} from '@/routes/workspace/invitations';
import {
  destroy as removeMember,
  update as updateMember,
} from '@/routes/workspace/members';

defineOptions({ layout: { title: trans('Members') } });

type Member = App.Data.Workspaces.WorkspaceMemberData;
type Invitation = App.Data.Workspaces.WorkspaceInvitationData;

const { roleLabels } = defineProps<{
  members: Member[];
  invitations: Invitation[];
  desks: App.Data.Desks.DeskData[];
  roleLabels: Record<App.Enums.WorkspaceRole, string>;
  roles: Record<App.Enums.WorkspaceRole, string>;
  inviteRoles: Record<App.Enums.WorkspaceRole, string>;
}>();

const page = usePage();

const inviteForm = useForm({
  email: '',
  role: 'agent' as App.Enums.WorkspaceRole,
  desk_id: null as string | null,
});

const removing = ref<Member | null>(null);
const revoking = ref<Invitation | null>(null);
const loading = ref(false);

function invite(): void {
  inviteForm.post(inviteMember().url, {
    preserveScroll: true,
    onSuccess: () => inviteForm.reset(),
  });
}

function changeRole(member: Member, role: App.Enums.WorkspaceRole): void {
  router.put(
    updateMember({ member: member.id }).url,
    { role },
    {
      preserveScroll: true,
    },
  );
}

function resend(invitation: Invitation): void {
  router.post(
    resendInvitation({ invitation: invitation.id }).url,
    {},
    {
      preserveScroll: true,
    },
  );
}

function remove(close: () => void): void {
  router.delete(removeMember({ member: removing.value!.id }).url, {
    preserveScroll: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
    onSuccess: close,
  });
}

function revoke(close: () => void): void {
  router.delete(revokeInvitation({ invitation: revoking.value!.id }).url, {
    preserveScroll: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <Head :title="$t('Members')" />

  <div class="space-y-6">
    <Table>
      <Thead>
        <tr>
          <Th>{{ $t('Name') }}</Th>
          <Th>{{ $t('Email') }}</Th>
          <Th>{{ $t('Role') }}</Th>
          <Th>{{ $t('Desks') }}</Th>
          <Th right>
            <span class="sr-only">{{ $t('Actions') }}</span>
          </Th>
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="member in members" :key="member.id">
          <Td darker>
            {{ member.name }}
            <Pill v-if="member.id === page.props.auth.user?.id" class="ms-2">{{
              $t('You')
            }}</Pill>
          </Td>
          <Td>{{ member.email }}</Td>
          <Td>
            <AppSelect
              v-if="member.role in roles"
              :model-value="member.role"
              :options="roles"
              hide-null
              :aria-label="$t('Role for :name', { name: member.name })"
              class="max-w-40"
              @update:model-value="
                changeRole(member, $event as App.Enums.WorkspaceRole)
              "
            />
            <template v-else>{{ roleLabels[member.role] }}</template>
          </Td>
          <Td>{{ member.desk_count }}</Td>
          <Td class="text-end">
            <AppButton
              type="button"
              size="xs"
              color="red"
              @click="removing = member"
              >{{
                member.id === page.props.auth.user?.id
                  ? $t('Leave')
                  : $t('Remove')
              }}</AppButton
            >
          </Td>
        </tr>
      </Tbody>
    </Table>

    <section v-if="invitations.length" class="space-y-2">
      <h2 class="text-sm font-semibold">{{ $t('Pending invitations') }}</h2>
      <Table>
        <Thead>
          <tr>
            <Th>{{ $t('Email') }}</Th>
            <Th>{{ $t('Role') }}</Th>
            <Th>{{ $t('Desk') }}</Th>
            <Th>{{ $t('Invited by') }}</Th>
            <Th>{{ $t('Sent') }}</Th>
            <Th>{{ $t('Expires') }}</Th>
            <Th right>
              <span class="sr-only">{{ $t('Actions') }}</span>
            </Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="invitation in invitations" :key="invitation.id">
            <Td darker>{{ invitation.email }}</Td>
            <Td>{{ roleLabels[invitation.role] }}</Td>
            <Td>{{ invitation.desk?.name ?? '—' }}</Td>
            <Td>{{ invitation.invited_by?.name ?? '—' }}</Td>
            <Td>{{ formatDate(invitation.created_at) }}</Td>
            <Td>{{ formatDate(invitation.expires_at) }}</Td>
            <Td class="space-x-2 text-end">
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="resend(invitation)"
                >{{ $t('Resend') }}</AppButton
              >
              <AppButton
                type="button"
                size="xs"
                color="red"
                @click="revoking = invitation"
                >{{ $t('Revoke') }}</AppButton
              >
            </Td>
          </tr>
        </Tbody>
      </Table>
    </section>

    <CardWrapper>
      <form @submit.prevent="invite">
        <CardPadding
          class="grid items-start gap-4 sm:grid-cols-[1fr_10rem_12rem_auto]"
        >
          <InputField
            v-model="inviteForm.email"
            :label="$t('Invite by email')"
            type="email"
            :error="inviteForm.errors.email"
            required
          />
          <SelectField
            v-model="inviteForm.role"
            :label="$t('Role')"
            :options="inviteRoles"
            :error="inviteForm.errors.role"
          />
          <div>
            <label
              for="invite-desk"
              class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
              >{{ $t('Desk') }}</label
            >
            <AppSelect
              id="invite-desk"
              v-model="inviteForm.desk_id"
              :options="desks"
              :null-option="$t('No desk')"
              clearable
            />
            <p
              v-if="inviteForm.errors.desk_id"
              class="mt-1 text-sm text-red-600"
            >
              {{ inviteForm.errors.desk_id }}
            </p>
          </div>
          <AppButton
            type="submit"
            class="sm:mt-6"
            :loading="inviteForm.processing"
            >{{ $t('Invite') }}</AppButton
          >
        </CardPadding>
      </form>
    </CardWrapper>
  </div>

  <ConfirmationModal
    v-if="removing"
    :loading="loading"
    @close="removing = null"
    @confirmed="remove"
  >
    <template #headline>{{
      removing.id === page.props.auth.user?.id
        ? $t('Leave this workspace?')
        : $t('Remove :name?', { name: removing.name })
    }}</template
    >{{
      removing.id === page.props.auth.user?.id
        ? $t(
            "You'll lose access, your open tickets will be unassigned, your workspace API tokens revoked, and you'll be signed out.",
          )
        : $t(
            'Their open tickets will be unassigned and their workspace API tokens revoked.',
          )
    }}<template #actionText>{{
      removing.id === page.props.auth.user?.id ? $t('Leave') : $t('Remove')
    }}</template>
  </ConfirmationModal>

  <ConfirmationModal
    v-if="revoking"
    :loading="loading"
    @close="revoking = null"
    @confirmed="revoke"
  >
    <template #headline>{{
      $t('Revoke the invitation for :email?', { email: revoking.email })
    }}</template
    >{{ $t('The link in their email will stop working.')
    }}<template #actionText>{{ $t('Revoke') }}</template>
  </ConfirmationModal>
</template>

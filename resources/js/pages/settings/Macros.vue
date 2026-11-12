<script setup lang="ts">
import { WandSparkles } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  CheckboxField,
  InputField,
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
import { destroy, store, update } from '@/routes/macros';

defineOptions({ layout: { title: trans('Macros') } });

type Macro = App.Data.Macros.MacroData;

const {
  macros,
  desks,
  editableIds,
  canCreateWorkspaceMacro,
  creatableDeskIds,
  statuses,
  priorities,
} = defineProps<{
  macros: Macro[];
  desks: App.Data.Desks.DeskData[];
  editableIds: string[];
  canCreateWorkspaceMacro: boolean;
  creatableDeskIds: string[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

const placeholders = [
  '{{ requester.name }}',
  '{{ requester.first_name }}',
  '{{ requester.email }}',
  '{{ ticket.number }}',
  '{{ ticket.subject }}',
  '{{ desk.name }}',
  '{{ agent.name }}',
  '{{ agent.first_name }}',
];

/** The macro being edited, `'new'` while creating one, or null when the modal is closed. */
const editing = ref<Macro | 'new' | null>(null);
const deleting = ref<Macro | null>(null);
const deleteLoading = ref(false);

const form = useForm({
  name: '',
  scope: 'personal' as App.Enums.MacroScope,
  desk_id: '',
  body: '',
  body_is_internal: false,
  status: '',
  priority: '',
  add_tags: '',
  remove_tags: '',
  assign_to_me: false,
});

const scopeOptions = computed<Record<string, string>>(() => ({
  personal: trans('Personal'),
  ...(canCreateWorkspaceMacro ? { workspace: trans('Workspace') } : {}),
  ...(creatableDeskIds.length ? { desk: trans('Desk') } : {}),
}));

const deskOptions = computed<Record<string, string>>(() =>
  Object.fromEntries(
    desks
      .filter((desk) => creatableDeskIds.includes(desk.id))
      .map((desk) => [desk.id, desk.name]),
  ),
);

const groups = computed(() => [
  {
    key: 'personal',
    title: trans('Personal'),
    macros: macros.filter((macro) => macro.scope === 'personal'),
  },
  {
    key: 'workspace',
    title: trans('Workspace'),
    macros: macros.filter((macro) => macro.scope === 'workspace'),
  },
  ...desks.map((desk) => ({
    key: desk.id,
    title: desk.name,
    macros: macros.filter((macro) => macro.desk_id === desk.id),
  })),
]);

const noChange = (options: Record<string, string>) => ({
  '': trans('No change'),
  ...options,
});

const tags = (value: string): string[] =>
  value
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean);

/** Item errors come back as `actions.add_tags.N`. */
const actionError = (key: string): string | undefined => {
  const errors: Record<string, string> = form.errors;

  return (
    errors[`actions.${key}`] ??
    Object.entries(errors).find(([name]) =>
      name.startsWith(`actions.${key}.`),
    )?.[1]
  );
};

function open(macro: Macro | 'new'): void {
  form.clearErrors();
  form.defaults({
    name: macro === 'new' ? '' : macro.name,
    scope: macro === 'new' ? 'personal' : macro.scope,
    desk_id: macro === 'new' ? '' : (macro.desk_id ?? ''),
    body: macro === 'new' ? '' : (macro.body ?? ''),
    body_is_internal: macro === 'new' ? false : macro.body_is_internal,
    status: macro === 'new' ? '' : (macro.actions.status ?? ''),
    priority: macro === 'new' ? '' : (macro.actions.priority ?? ''),
    add_tags: macro === 'new' ? '' : macro.actions.add_tags.join(', '),
    remove_tags: macro === 'new' ? '' : macro.actions.remove_tags.join(', '),
    assign_to_me: macro === 'new' ? false : macro.actions.assign_to_me,
  });
  form.reset();
  editing.value = macro;
}

function save(close: () => void): void {
  const macro = editing.value;

  form
    .transform(
      ({
        scope,
        desk_id,
        status,
        priority,
        add_tags,
        remove_tags,
        assign_to_me,
        body,
        ...data
      }) => ({
        ...data,
        // A macro's scope can't change once it exists.
        ...(macro === 'new'
          ? { scope, ...(scope === 'desk' ? { desk_id } : {}) }
          : {}),
        body: body.trim() === '' ? null : body,
        actions: {
          ...(status ? { status } : {}),
          ...(priority ? { priority } : {}),
          ...(tags(add_tags).length ? { add_tags: tags(add_tags) } : {}),
          ...(tags(remove_tags).length
            ? { remove_tags: tags(remove_tags) }
            : {}),
          ...(assign_to_me ? { assign_to_me } : {}),
        },
      }),
    )
    .submit(macro === 'new' || macro === null ? store() : update(macro.id), {
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
  <Head :title="$t('Macros')" />

  <SettingsNav />

  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ $t('Canned responses that can also change a ticket.') }}
      </p>
      <AppButton type="button" class="shrink-0" @click="open('new')">{{
        $t('New macro')
      }}</AppButton>
    </div>

    <template v-for="group in groups" :key="group.key">
      <section v-if="group.macros.length" class="space-y-2">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
          {{ group.title }}
        </h2>
        <Table>
          <Thead>
            <tr>
              <Th>{{ $t('Name') }}</Th>
              <Th>{{ $t('Reply') }}</Th>
              <Th>{{ $t('Actions') }}</Th>
              <Th right
                ><span class="sr-only">{{ $t('Actions') }}</span></Th
              >
            </tr>
          </Thead>
          <Tbody>
            <tr v-for="macro in group.macros" :key="macro.id">
              <Td darker>{{ macro.name }}</Td>
              <Td>
                <Pill v-if="macro.body" color="gray">
                  {{
                    macro.body_is_internal ? $t('Internal note') : $t('Reply')
                  }}
                </Pill>
                <span v-else class="text-gray-400">—</span>
              </Td>
              <Td>
                <span class="flex flex-wrap gap-1">
                  <Pill v-if="macro.actions.status" color="gray">
                    {{ statuses[macro.actions.status] }}
                  </Pill>
                  <Pill v-if="macro.actions.priority" color="gray">
                    {{ priorities[macro.actions.priority] }}
                  </Pill>
                  <Pill v-if="macro.actions.assign_to_me" color="gray">{{
                    $t('Assign to me')
                  }}</Pill>
                  <Pill
                    v-if="
                      macro.actions.add_tags.length ||
                      macro.actions.remove_tags.length
                    "
                    color="gray"
                    >{{ $t('Tags') }}</Pill
                  >
                </span>
              </Td>
              <Td class="space-x-2 text-end whitespace-nowrap">
                <template v-if="editableIds.includes(macro.id)">
                  <AppButton
                    type="button"
                    size="xs"
                    color="white"
                    @click="open(macro)"
                    >{{ $t('Edit') }}</AppButton
                  >
                  <AppButton
                    type="button"
                    size="xs"
                    color="red"
                    @click="deleting = macro"
                    >{{ $t('Delete') }}</AppButton
                  >
                </template>
              </Td>
            </tr>
          </Tbody>
        </Table>
      </section>
    </template>
    <EmptyState
      v-if="!macros.length"
      :icon="WandSparkles"
      :title="$t('There are no macros yet.')"
    />
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? $t('New macro') : $t('Edit macro')"
    :action-loading="form.processing"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="form.name"
          :label="$t('Name')"
          :error="form.errors.name"
          required
        />
        <SelectField
          v-model="form.scope"
          :label="$t('Scope')"
          :options="scopeOptions"
          :error="form.errors.scope"
          :disabled="editing !== 'new'"
          :help-text="
            editing !== 'new'
              ? $t('The scope can’t be changed after a macro is created.')
              : undefined
          "
          required
        />
        <SelectField
          v-if="form.scope === 'desk'"
          v-model="form.desk_id"
          :label="$t('Desk')"
          :options="
            editing === 'new'
              ? deskOptions
              : Object.fromEntries(desks.map((desk) => [desk.id, desk.name]))
          "
          :error="form.errors.desk_id"
          :disabled="editing !== 'new'"
          required
        />
        <TextareaField
          v-model="form.body"
          :label="$t('Body')"
          :error="form.errors.body"
          :help-text="
            $t('Leave empty for a macro that only changes the ticket.')
          "
        />
        <p class="flex flex-wrap gap-1 text-xs text-gray-500">
          <code
            v-for="placeholder in placeholders"
            :key="placeholder"
            class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800"
          >
            {{ placeholder }}
          </code>
        </p>
        <CheckboxField
          v-model="form.body_is_internal"
          :label="$t('Add as an internal note instead of a reply')"
          :error="form.errors.body_is_internal"
        />
        <SelectField
          v-model="form.status"
          :label="$t('Set status')"
          :options="noChange(statuses)"
          :error="actionError('status')"
        />
        <SelectField
          v-model="form.priority"
          :label="$t('Set priority')"
          :options="noChange(priorities)"
          :error="actionError('priority')"
        />
        <InputField
          v-model="form.add_tags"
          :label="$t('Add tags')"
          :help-text="$t('Separate tags with commas.')"
          :error="actionError('add_tags')"
        />
        <InputField
          v-model="form.remove_tags"
          :label="$t('Remove tags')"
          :help-text="$t('Separate tags with commas.')"
          :error="actionError('remove_tags')"
        />
        <CheckboxField
          v-model="form.assign_to_me"
          :label="$t('Assign the ticket to me')"
          :error="actionError('assign_to_me')"
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
    <template #headline>{{
      $t('Delete “:name”?', { name: deleting.name })
    }}</template
    >{{ $t('Agents can no longer apply it to tickets.')
    }}<template #actionText>{{ $t('Delete macro') }}</template>
  </ConfirmationModal>
</template>

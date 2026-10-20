<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import DropDownButton from '@/components/forms/buttons/DropDownButton.vue';
import FieldError from '@/components/forms/FieldError.vue';
import CheckboxField from '@/components/forms/fields/CheckboxField.vue';
import InputField from '@/components/forms/fields/InputField.vue';
import Modal from '@/components/modals/Modal.vue';
import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';
import { cleanQuery, sameQuery } from '@/lib/tickets';
import { destroy, store, update } from '@/routes/saved-views';

const { views, filters } = defineProps<{
  views: App.Data.SavedViews.SavedViewData[];
  filters: TicketQuery;
}>();

const emit = defineEmits<{ apply: [filters: TicketQuery] }>();

const page = usePage();

/** The view whose filters match the list right now, if any. */
const activeView = computed(() =>
  views.find((view) => sameQuery(cleanQuery(view.filters), filters)),
);

function isOwner(view: App.Data.SavedViews.SavedViewData): boolean {
  return view.user_id === page.props.auth.user.id;
}

/** Mirrors `SavedViewPolicy@update`. */
function canEdit(view: App.Data.SavedViews.SavedViewData): boolean {
  return isOwner(view) || (view.is_shared && !!page.props.workspace?.is_admin);
}

const editing = ref<App.Data.SavedViews.SavedViewData | 'new' | null>(null);
const form = useForm({ name: '', is_shared: false });
const deleting = ref(false);

/** Filters aren't a form field, but the server can still reject them. */
const filtersError = computed(
  () => (form.errors as Record<string, string | undefined>).filters,
);

function open(view: App.Data.SavedViews.SavedViewData | 'new'): void {
  form.defaults({
    name: view === 'new' ? '' : view.name,
    is_shared: view === 'new' ? false : view.is_shared,
  });
  form.reset();
  form.clearErrors();
  editing.value = view;
}

function save(close: () => void): void {
  const view = editing.value;

  if (view === 'new') {
    form
      .transform((data) => ({ ...data, filters }))
      .submit(store(), { preserveScroll: true, onSuccess: close });
  } else if (view) {
    // Only the owner decides whether a view is shared.
    form
      .transform((data) => (isOwner(view) ? data : { name: data.name }))
      .submit(update(view.id), { preserveScroll: true, onSuccess: close });
  }
}

function remove(
  view: App.Data.SavedViews.SavedViewData,
  close: () => void,
): void {
  router.delete(destroy(view.id).url, {
    preserveScroll: true,
    onStart: () => (deleting.value = true),
    onFinish: () => (deleting.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <div class="flex items-center gap-2">
    <DropDownButton size="base">
      {{ activeView?.name ?? 'Views' }}
      <template #dropdown>
        <div class="max-h-80 overflow-y-auto p-1">
          <AppMenuItem
            v-for="view in views"
            :key="view.id"
            is="button"
            type="button"
            class="justify-between gap-2 text-left"
            @click="emit('apply', cleanQuery(view.filters))"
          >
            <span class="truncate">{{ view.name }}</span>
            <span
              v-if="view.is_shared"
              class="shrink-0 text-xs opacity-70"
              :title="`Shared by ${view.owner?.name}`"
            >
              {{ isOwner(view) ? 'Shared' : view.owner?.name }}
            </span>
          </AppMenuItem>
          <p
            v-if="!views.length"
            class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400"
          >
            No saved views yet.
          </p>
        </div>
      </template>
    </DropDownButton>

    <AppButton
      v-if="activeView && canEdit(activeView)"
      type="button"
      color="white"
      @click="open(activeView)"
    >
      Edit view
    </AppButton>
    <AppButton
      v-else-if="!activeView"
      type="button"
      color="white"
      @click="open('new')"
    >
      Save view
    </AppButton>

    <Modal
      v-if="editing"
      :headline="editing === 'new' ? 'Save view' : 'Edit view'"
      @close="editing = null"
    >
      <form class="mt-4" @submit.prevent>
        <AppFieldset>
          <InputField
            v-model="form.name"
            label="Name"
            :error="form.errors.name"
            required
          />
          <CheckboxField
            v-if="editing === 'new' || isOwner(editing)"
            v-model="form.is_shared"
            label="Share with the workspace"
            help-text="Everyone in the workspace can pick a shared view."
            :error="form.errors.is_shared"
          />
        </AppFieldset>
        <FieldError v-if="filtersError" class="mt-2">
          {{ filtersError }}
        </FieldError>
      </form>

      <template #actions="{ close }">
        <AppButton
          type="button"
          :loading="form.processing"
          @click="save(close)"
        >
          {{ editing === 'new' ? 'Save view' : 'Save' }}
        </AppButton>
        <AppButton type="button" color="white" @click="close">Cancel</AppButton>
        <AppButton
          v-if="editing !== 'new'"
          type="button"
          color="red"
          class="sm:mr-auto"
          :loading="deleting"
          @click="remove(editing, close)"
        >
          Delete view
        </AppButton>
      </template>
    </Modal>
  </div>
</template>

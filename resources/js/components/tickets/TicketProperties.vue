<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import AssigneePicker from '@/components/tickets/AssigneePicker.vue';
import CustomFieldInput from '@/components/tickets/CustomFieldInput.vue';
import { formatDuration } from '@/lib/dates';
import { show as showContact } from '@/routes/contacts';
import { update as updateTicket } from '@/routes/tickets';
import { update as moveTicket } from '@/routes/tickets/desk';
import { update as syncAssignees } from '@/routes/tickets/assignees';
import { update as syncTags } from '@/routes/tickets/tags';
import type { CustomFieldValue } from '@/types';

const { ticket } = defineProps<{
  ticket: App.Data.Tickets.TicketData;
  desks: App.Data.Desks.DeskData[];
  deskMembers: App.Data.Desks.DeskMemberData[];
  tags: App.Data.Tags.TagData[];
  editable: boolean;
}>();

const errors = ref<Record<string, string>>({});
const newTag = ref('');

/** Every sidebar change saves straight away and reloads the ticket. */
function save(
  url: string,
  data: Record<string, unknown>,
  {
    method = 'put',
    onSuccess,
  }: { method?: 'put' | 'patch'; onSuccess?: () => void } = {},
): void {
  router.visit(url, {
    method,
    data: data as Record<string, string>,
    preserveScroll: true,
    onSuccess: () => {
      errors.value = {};
      onSuccess?.();
    },
    onError: (bag) => (errors.value = bag),
  });
}

const fields = ticket.desk?.custom_fields ?? [];

/** Multi-selects need a list and checkboxes a boolean even before they're set. */
function initialValue(
  field: App.Data.CustomFields.CustomFieldData,
): CustomFieldValue {
  const value = ticket.custom_fields[field.id];

  if (field.type === 'multi_select') {
    return value ?? [];
  }

  return field.type === 'checkbox' ? (value ?? false) : (value ?? null);
}

const fieldValues = ref<Record<string, CustomFieldValue>>(
  Object.fromEntries(fields.map((field) => [field.id, initialValue(field)])),
);
let savedValues = JSON.stringify(fieldValues.value);

// Selects and checkboxes save at once; typing waits for a pause.
watchDebounced(
  fieldValues,
  (values) => {
    const saved = JSON.parse(savedValues) as Record<string, CustomFieldValue>;
    const changed = Object.fromEntries(
      Object.entries(values).filter(
        ([id, value]) => JSON.stringify(value) !== JSON.stringify(saved[id]),
      ),
    );

    if (Object.keys(changed).length) {
      // Only count values as saved once the server accepts them, so a rejected value
      // is sent again when it's retyped.
      const snapshot = JSON.stringify(values);

      save(
        updateTicket(ticket.id).url,
        { custom_fields: changed },
        { method: 'patch', onSuccess: () => (savedValues = snapshot) },
      );
    }
  },
  { debounce: 500, deep: true },
);

function saveAssignees(ids: string[]): void {
  save(syncAssignees(ticket.id).url, { user_ids: ids });
}

function tagNames(): string[] {
  return (ticket.tags ?? []).map((tag) => tag.name);
}

function addTag(): void {
  const name = newTag.value.trim();

  if (name) {
    save(syncTags(ticket.id).url, { names: [...tagNames(), name] });
    newTag.value = '';
  }
}

function removeTag(name: string): void {
  save(syncTags(ticket.id).url, {
    names: tagNames().filter((tag) => tag !== name),
  });
}

/** Breached is red, then met green, then warned amber. */
function slaColor(target: App.Data.Tickets.TicketSlaTargetData): string {
  if (target.breached_at) {
    return 'text-red-600 dark:text-red-400';
  }

  if (target.achieved_at) {
    return 'text-green-600 dark:text-green-400';
  }

  return target.warned_at
    ? 'text-amber-600 dark:text-amber-400'
    : 'text-gray-900 dark:text-gray-100';
}
</script>

<template>
  <CardWrapper>
    <CardPadding class="space-y-5 text-sm">
      <div>
        <h2 class="font-medium text-gray-500 dark:text-gray-400">
          {{ $t('Requester') }}
        </h2>
        <Link
          v-if="ticket.requester"
          :href="showContact(ticket.requester.id)"
          class="mt-1 block text-primary-600 hover:underline dark:text-primary-400"
        >
          {{ ticket.requester.name }}
        </Link>
        <p class="text-gray-500 dark:text-gray-400">
          {{ ticket.requester?.email }}
        </p>
      </div>

      <div class="space-y-1">
        <AppLabel for="ticket-desk">{{ $t('Desk') }}</AppLabel>
        <AppSelect
          id="ticket-desk"
          :model-value="ticket.desk_id"
          :options="desks"
          hide-null
          :disabled="!editable"
          :has-error="!!errors.desk_id"
          @update:model-value="
            save(moveTicket(ticket.id).url, { desk_id: String($event) })
          "
        />
        <FieldError v-if="errors.desk_id">{{ errors.desk_id }}</FieldError>
      </div>

      <div v-if="ticket.satisfaction_rating" class="space-y-1">
        <h2 class="font-medium text-gray-500 dark:text-gray-400">
          {{ $t('Satisfaction') }}
        </h2>
        <Pill
          :color="
            ticket.satisfaction_rating.rating === 'good' ? 'green' : 'red'
          "
        >
          {{ ticket.satisfaction_rating.rating_label }}
        </Pill>
        <p
          v-if="ticket.satisfaction_rating.comment"
          class="whitespace-pre-wrap"
        >
          {{ ticket.satisfaction_rating.comment }}
        </p>
      </div>

      <div v-if="ticket.sla_targets?.length" class="space-y-1">
        <h2 class="font-medium text-gray-500 dark:text-gray-400">
          {{ $t('SLA') }}
        </h2>
        <p
          v-for="target in ticket.sla_targets"
          :key="target.metric"
          :class="slaColor(target)"
        >
          {{ target.metric_label }}
          <template v-if="target.achieved_at">
            {{
              $t(
                target.breached_at
                  ? 'met in :duration (breached)'
                  : 'met in :duration',
                {
                  duration: formatDuration(
                    target.started_at,
                    target.achieved_at,
                  ),
                },
              )
            }}
          </template>
          <i18n-t
            v-else-if="target.breached_at"
            keypath="breached :time"
            tag="span"
          >
            <template #time>
              <RelativeTime :datetime="target.breached_at" />
            </template>
          </i18n-t>
          <template v-else-if="target.paused || !target.due_at">{{
            $t('paused')
          }}</template>
          <i18n-t v-else keypath="due :time" tag="span">
            <template #time
              ><RelativeTime :datetime="target.due_at"
            /></template>
          </i18n-t>
        </p>
      </div>

      <div class="space-y-2">
        <h2 class="font-medium text-gray-500 dark:text-gray-400">
          {{ $t('Assignees') }}
        </h2>
        <AssigneePicker
          :model-value="(ticket.assignees ?? []).map((agent) => agent.id)"
          :members="deskMembers"
          :disabled="!editable"
          @update:model-value="saveAssignees"
        />
        <FieldError v-if="errors.user_ids">{{ errors.user_ids }}</FieldError>
      </div>

      <div class="space-y-2">
        <h2 class="font-medium text-gray-500 dark:text-gray-400">
          {{ $t('Tags') }}
        </h2>
        <div v-if="ticket.tags?.length" class="flex flex-wrap gap-1">
          <Pill v-for="tag in ticket.tags" :key="tag.id">
            {{ tag.name }}
            <button
              v-if="editable"
              type="button"
              class="-mr-1 ml-1 rounded-full hover:text-red-600"
              @click="removeTag(tag.name)"
            >
              <span class="sr-only">{{
                $t('Remove :name', { name: tag.name })
              }}</span>
              <X class="size-3" aria-hidden="true" />
            </button>
          </Pill>
        </div>
        <form v-if="editable" @submit.prevent="addTag">
          <!-- A native datalist suggests existing tags; new names create a tag. -->
          <AppInput
            v-model="newTag"
            list="ticket-tag-options"
            :placeholder="$t('Add a tag and press Enter')"
            :aria-label="$t('Add a tag')"
          />
          <datalist id="ticket-tag-options">
            <option v-for="tag in tags" :key="tag.id" :value="tag.name" />
          </datalist>
        </form>
        <p v-else-if="!ticket.tags?.length" class="text-gray-400">
          {{ $t('None') }}
        </p>
        <FieldError v-if="errors.names">{{ errors.names }}</FieldError>
      </div>

      <CustomFieldInput
        v-for="field in fields"
        :key="field.id"
        v-model="fieldValues[field.id]"
        :field="field"
        :error="errors[`custom_fields.${field.id}`]"
        :disabled="!editable"
      />
    </CardPadding>
  </CardWrapper>
</template>

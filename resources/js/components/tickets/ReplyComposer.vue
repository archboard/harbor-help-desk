<script setup lang="ts">
import { router, useForm, useHttp } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { useDropZone, useTimeoutFn } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import AppButton from '@/components/AppButton.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import GenericObjectCombobox from '@/components/forms/GenericObjectCombobox.vue';
import FieldError from '@/components/forms/FieldError.vue';
import FilePicker from '@/components/forms/FilePicker.vue';
import FadeIn from '@/components/transitions/FadeIn.vue';
import { firstListError } from '@/lib/files';
import { quickViewDraft } from '@/lib/quickView';
import { apply } from '@/routes/tickets/macros';
import { store } from '@/routes/tickets/comments';

const {
  ticketId,
  deskMembers,
  macros,
  lastSeenCommentId,
  initialBody = '',
  reloadOnly,
} = defineProps<{
  ticketId: string;
  deskMembers: App.Data.Desks.DeskMemberData[];
  /** Undefined while the deferred prop loads. */
  macros?: App.Data.Macros.MacroData[];
  /** The newest comment rendered, or null when there are none. */
  lastSeenCommentId: string | null;
  /** Seeds the body, e.g. with a macro's text. */
  initialBody?: string;
  /** The props that reload after a macro is applied or a newer comment is shown. */
  reloadOnly: string[];
}>();

/** True while the body is non-empty and was edited in the last 3 seconds. */
const typing = defineModel<boolean>('typing', { default: false });
const { start: startIdleTimer } = useTimeoutFn(
  () => (typing.value = false),
  3000,
  { immediate: false },
);

const form = useForm({
  body: initialBody,
  last_seen_comment_id: null as string | null,
  is_internal: false as boolean,
  attachments: [] as File[],
} satisfies App.Data.Tickets.AddTicketCommentData);

// A draft handed over by the quick view (a macro run from the command palette).
watch(
  quickViewDraft,
  (draft) => {
    if (draft?.ticketId === ticketId) {
      form.body = draft.body;
      form.is_internal = draft.isInternal;
      quickViewDraft.value = null;
    }
  },
  { immediate: true },
);

watch(
  () => form.body,
  (body) => {
    typing.value = body !== '';

    if (typing.value) {
      startIdleTimer();
    }
  },
);

/**
 * Applying a macro runs its actions on the server right away. Its body is appended to the
 * composer for the agent to edit and send, and the composer switches to match.
 */
const pickingMacro = ref(false);
const macroError = ref('');
const http = useHttp({});

async function toggleMacroPicker(): Promise<void> {
  pickingMacro.value = !pickingMacro.value;

  if (pickingMacro.value) {
    await nextTick();
    document.getElementById('macro-picker')?.focus();
  }
}

async function applyMacro(
  macro: App.Data.Macros.MacroData | null,
): Promise<void> {
  if (!macro) {
    return;
  }

  macroError.value = '';

  try {
    const applied = (await http.post(
      apply.url({ ticket: ticketId, macro: macro.id }),
    )) as App.Data.Macros.AppliedMacroData;

    if (applied.body) {
      form.body = form.body ? `${form.body}\n\n${applied.body}` : applied.body;
      form.is_internal = applied.is_internal;
    }

    pickingMacro.value = false;
    router.reload({ only: reloadOnly });
  } catch {
    macroError.value =
      Object.values<string>(http.errors)[0] ??
      trans('The macro could not be applied.');
  }
}

function addFiles(files: Iterable<File> | null): void {
  form.attachments = [...form.attachments, ...Array.from(files ?? [])];
}

/**
 * Show the drop target while files (not text or links) are dragged anywhere over the page.
 * `isOverDropZone` counts enters and leaves, so moving between elements doesn't flicker;
 * `onLeave` fires on every element boundary, so it's not used. Dropping outside the
 * composer is swallowed so the browser doesn't open the file.
 */
const draggedFiles = ref(false);

const { isOverDropZone: isOverPage } = useDropZone(document.body, {
  onEnter: (_, event) =>
    (draggedFiles.value = event.dataTransfer?.types.includes('Files') ?? false),
});

const draggingFiles = computed(() => isOverPage.value && draggedFiles.value);

const composer = useTemplateRef<HTMLElement>('composer');
const { isOverDropZone: isOverComposer } = useDropZone(composer, addFiles);

/**
 * Typing `@` in an internal note suggests desk members. Picking one inserts `@Name` and tracks
 * the id; on submit, ids whose `@Name` was deleted from the body are dropped.
 */
const mentionedMembers = ref<App.Data.Desks.DeskMemberData[]>([]);
const mention = ref<{ query: string; start: number } | null>(null);
const activeSuggestion = ref(0);

const suggestions = computed(() =>
  mention.value && form.is_internal
    ? deskMembers
        .filter((member) =>
          member.name
            .toLowerCase()
            .includes(mention.value!.query.toLowerCase()),
        )
        .slice(0, 5)
    : [],
);

function detectMention(event: Event): void {
  const textarea = event.target as HTMLTextAreaElement;
  const found = /(?:^|\s)@([^\s@]*)$/.exec(
    textarea.value.slice(0, textarea.selectionStart),
  );

  mention.value = found
    ? { query: found[1], start: textarea.selectionStart - found[1].length - 1 }
    : null;
  activeSuggestion.value = 0;
}

function pick(member: App.Data.Desks.DeskMemberData): void {
  if (!mention.value) {
    return;
  }

  const { query, start } = mention.value;

  form.body = `${form.body.slice(0, start)}@${member.name} ${form.body.slice(start + 1 + query.length)}`;

  if (!mentionedMembers.value.some(({ id }) => id === member.id)) {
    mentionedMembers.value.push(member);
  }

  mention.value = null;
}

function navigateSuggestions(event: KeyboardEvent): void {
  if (suggestions.value.length === 0) {
    return;
  }

  const count = suggestions.value.length;

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    activeSuggestion.value =
      (activeSuggestion.value + (event.key === 'ArrowDown' ? 1 : count - 1)) %
      count;
  } else if (event.key === 'Enter') {
    pick(suggestions.value[activeSuggestion.value]);
  } else if (event.key === 'Escape') {
    mention.value = null;
  } else {
    return;
  }

  event.preventDefault();
}

/** Set to skip the stale-reply check after the agent chose "Send anyway". */
function send(skipStaleCheck = false): void {
  form
    .transform(({ last_seen_comment_id: _, ...data }) => ({
      ...data,
      ...(skipStaleCheck ? {} : { last_seen_comment_id: lastSeenCommentId }),
      mentioned_user_ids: data.is_internal
        ? mentionedMembers.value
            .filter(({ name }) => data.body.includes(`@${name}`))
            .map(({ id }) => id)
        : [],
    }))
    .submit(store(ticketId), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        form.clearErrors();
        mentionedMembers.value = [];
      },
    });
}
</script>

<template>
  <form
    ref="composer"
    :class="[
      'relative space-y-3 rounded-lg p-3 ring-1 ring-inset',
      form.is_internal
        ? 'bg-yellow-50 ring-yellow-300 dark:bg-yellow-950 dark:ring-yellow-800'
        : 'bg-white ring-gray-300 dark:bg-gray-900 dark:ring-gray-700',
    ]"
    @submit.prevent="send()"
  >
    <div class="flex items-center gap-1">
      <div
        class="flex gap-1"
        role="radiogroup"
        :aria-label="$t('Message type')"
      >
        <button
          v-for="option in [
            { internal: false, label: $t('Reply') },
            { internal: true, label: $t('Internal note') },
          ]"
          :key="option.label"
          type="button"
          role="radio"
          :aria-checked="form.is_internal === option.internal"
          :class="[
            'rounded-md px-3 py-1.5 text-sm font-medium',
            form.is_internal === option.internal
              ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
          ]"
          @click="form.is_internal = option.internal"
        >
          {{ option.label }}
        </button>
      </div>
      <button
        type="button"
        class="ml-auto rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 disabled:animate-pulse disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-800"
        :disabled="macros === undefined || http.processing"
        @click="toggleMacroPicker"
      >
        {{ $t('Macros') }}
      </button>
    </div>

    <div v-if="pickingMacro && macros" class="space-y-1">
      <GenericObjectCombobox
        id="macro-picker"
        :options="macros as unknown as Record<string, unknown>[]"
        @update:model-value="
          applyMacro($event as App.Data.Macros.MacroData | null)
        "
      />
      <FieldError v-if="macroError">{{ macroError }}</FieldError>
    </div>

    <div
      class="relative space-y-1"
      @input="detectMention"
      @keydown="navigateSuggestions"
      @keydown.meta.enter.exact.prevent="!form.processing && send()"
      @keydown.ctrl.enter.exact.prevent="!form.processing && send()"
    >
      <AppTextarea
        v-model="form.body"
        :aria-label="form.is_internal ? $t('Internal note') : $t('Reply')"
        :placeholder="
          form.is_internal
            ? $t('Only agents can see internal notes')
            : $t('Write a reply')
        "
        :has-error="!!form.errors.body"
      />
      <ul
        v-if="suggestions.length > 0"
        class="absolute z-10 w-64 rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 dark:bg-gray-900"
        role="listbox"
        :aria-label="$t('Mention an agent')"
      >
        <li
          v-for="(member, index) in suggestions"
          :key="member.id"
          role="option"
          :aria-selected="index === activeSuggestion"
          :class="[
            'cursor-default px-3 py-1.5 select-none',
            index === activeSuggestion
              ? 'bg-primary-600 text-white'
              : 'text-gray-900 dark:text-gray-100',
          ]"
          @mousedown.prevent="pick(member)"
        >
          {{ member.name }}
        </li>
      </ul>
      <FieldError v-if="form.errors.body">{{ form.errors.body }}</FieldError>
      <FieldError v-if="firstListError(form.errors, 'mentioned_user_ids')">
        {{ firstListError(form.errors, 'mentioned_user_ids') }}
      </FieldError>
    </div>

    <FieldError v-if="firstListError(form.errors, 'attachments')">
      {{ firstListError(form.errors, 'attachments') }}
    </FieldError>

    <SimpleAlert v-if="form.errors.last_seen_comment_id" level="warning">
      {{ form.errors.last_seen_comment_id }}
      <button
        type="button"
        class="ml-2 underline"
        @click="
          router.reload({
            only: reloadOnly,
            onSuccess: () => form.clearErrors('last_seen_comment_id'),
          })
        "
      >
        {{ $t('Show it') }}
      </button>
      <button type="button" class="ml-2 underline" @click="send(true)">
        {{ $t('Send anyway') }}
      </button>
    </SimpleAlert>

    <div class="flex items-end justify-between gap-3">
      <FilePicker v-model="form.attachments" />
      <AppButton type="submit" :loading="form.processing">
        {{ form.is_internal ? $t('Add note') : $t('Send reply') }}
      </AppButton>
    </div>

    <FadeIn>
      <div
        v-if="draggingFiles"
        :class="[
          'pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed text-sm font-medium backdrop-blur-sm',
          isOverComposer
            ? 'border-primary-500 bg-primary-50/90 text-primary-700 dark:bg-primary-950/90 dark:text-primary-200'
            : 'border-gray-300 bg-white/80 text-gray-600 dark:border-gray-600 dark:bg-gray-900/80 dark:text-gray-300',
        ]"
      >
        <Upload class="size-6" aria-hidden="true" />
        {{ $t('Drop files to attach them') }}
      </div>
    </FadeIn>
  </form>
</template>

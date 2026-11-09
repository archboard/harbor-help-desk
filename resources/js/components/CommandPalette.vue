<script setup lang="ts">
import {
  Combobox,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
  Dialog,
  DialogPanel,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { ChevronRight, Search } from '@lucide/vue';
import { useMagicKeys, watchDebounced } from '@vueuse/core';
import { computed, ref, shallowRef, watch } from 'vue';
import Spinner from '@/components/Spinner.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import { paletteOpen, paletteTarget } from '@/lib/commandPalette';
import type { Command, PaletteTarget } from '@/lib/commandPalette';
import { useNavigation } from '@/lib/navigation';
import { openQuickView } from '@/lib/quickView';
import { ticketCommands } from '@/lib/ticketCommands';
import { create as createTicket, lookup, show } from '@/routes/tickets';
import { apply as applyMacroRoute } from '@/routes/tickets/macros';

const { navigation, userNavigation } = useNavigation();

const query = ref('');
const stack = ref<Command[]>([]);
const tickets = shallowRef<App.Data.Tickets.TicketData[]>([]);
const http = useHttp({ search: '' });
const macroHttp = useHttp({});
const page = usePage();
const palette = computed(() => page.props.palette);
/** The tickets the page scoped this `Cmd+K` to, if any. */
const target = ref<PaletteTarget | null>(null);

const keys = useMagicKeys({
  passive: false,
  onEventFired(event) {
    if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) {
      event.preventDefault();
    }
  },
});

watch(
  () => keys['Meta+K'].value || keys['Ctrl+K'].value,
  (pressed) => {
    if (pressed) {
      target.value = paletteOpen.value
        ? null
        : (paletteTarget.value?.() ?? null);
      paletteOpen.value = !paletteOpen.value;
    }
  },
);

watch(paletteOpen, (open) => {
  if (!open) {
    query.value = '';
    stack.value = [];
    tickets.value = [];
    target.value = null;
  } else if (!palette.value) {
    router.reload({ only: ['palette'] });
  }
});

watch(stack, () => {
  query.value = '';
});

/** Applies the macro on the server, then opens the quick view with its text as the draft. */
async function applyMacro(
  ticketId: string,
  macro: App.Data.Macros.MacroData,
): Promise<void> {
  try {
    const applied = (await macroHttp.post(
      applyMacroRoute.url({ ticket: ticketId, macro: macro.id }),
    )) as App.Data.Macros.AppliedMacroData;

    openQuickView(
      ticketId,
      applied.body
        ? { body: applied.body, isInternal: applied.is_internal }
        : undefined,
    );
  } catch {
    router.reload();
  }
}

function actionsFor(target: PaletteTarget): Command[] {
  return ticketCommands({
    target,
    palette: palette.value,
    userId: page.props.auth.user.id,
    applyMacro,
  });
}

function ticketCommand(ticket: App.Data.Tickets.TicketData): Command {
  return {
    id: ticket.id,
    label: `#${ticket.number} ${ticket.subject}`,
    group: 'Tickets',
    ticket,
    children: () => [
      {
        id: 'open',
        label: 'Open',
        group: `Ticket #${ticket.number}`,
        run: () => router.visit(show(ticket.id)),
      },
      {
        id: 'quick-view',
        label: 'Quick view',
        group: `Ticket #${ticket.number}`,
        run: () => openQuickView(ticket.id),
      },
      ...actionsFor({ ticketIds: [ticket.id], ticket }),
    ],
  };
}

const rootCommands = computed<Command[]>(() => [
  ...(target.value ? actionsFor(target.value) : []),
  ...[
    ...navigation.value,
    ...userNavigation.value,
    { label: 'New ticket', href: createTicket().url },
  ].map((item) => ({
    id: item.href,
    label: item.label,
    group: 'Pages',
    run: () => router.visit(item.href),
  })),
  ...tickets.value.map(ticketCommand),
]);

const current = computed(() => stack.value.at(-1));

const commands = computed(() => {
  const term = query.value.trim().toLowerCase();
  const all = current.value
    ? current.value.children!(query.value)
    : rootCommands.value;

  // Tickets are already filtered by the server.
  return all.filter(
    (command) => command.ticket || command.label.toLowerCase().includes(term),
  );
});

const groups = computed(() =>
  Object.entries(Object.groupBy(commands.value, (command) => command.group)),
);

/** A nested level of ticket actions is empty until the `palette` prop arrives. */
const loadingActions = computed(() => !!current.value && !palette.value);

/** The query the ticket results belong to; differs from `query` while a search is pending. */
const searchedTerm = ref('');
const searching = computed(
  () =>
    !current.value &&
    query.value.trim() !== '' &&
    searchedTerm.value !== query.value,
);

watchDebounced(
  query,
  async (term) => {
    if (current.value) {
      return;
    }

    if (term.trim() === '') {
      tickets.value = [];

      return;
    }

    http.search = term;
    let response: App.Data.Tickets.TicketData[] = [];

    try {
      response = (await http.get(
        lookup.url(),
      )) as App.Data.Tickets.TicketData[];
    } finally {
      if (term === query.value) {
        tickets.value = response;
        searchedTerm.value = term;
      }
    }
  },
  { debounce: 300 },
);

function choose(command: Command | null): void {
  if (!command) {
    return;
  }

  if (command.children) {
    stack.value = [...stack.value, command];

    return;
  }

  paletteOpen.value = false;
  command.run?.();
}

function goBack(event: KeyboardEvent): void {
  if (query.value === '' && stack.value.length > 0) {
    event.preventDefault();
    stack.value = stack.value.slice(0, -1);
  }
}
</script>

<template>
  <TransitionRoot as="template" :show="paletteOpen">
    <Dialog class="relative z-50" @close="paletteOpen = false">
      <TransitionChild
        as="template"
        enter="duration-200 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-150 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-800/75 backdrop-blur-xs" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto p-4 pt-[15vh]">
        <TransitionChild
          as="template"
          enter="duration-200 ease-out"
          enter-from="scale-95 opacity-0"
          enter-to="scale-100 opacity-100"
          leave="duration-150 ease-in"
          leave-from="scale-100 opacity-100"
          leave-to="scale-95 opacity-0"
        >
          <DialogPanel
            class="mx-auto max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
          >
            <Combobox :model-value="null" nullable @update:model-value="choose">
              <div
                class="flex items-center gap-2 border-b border-gray-200 px-4 dark:border-gray-700"
              >
                <Spinner
                  v-if="searching || loadingActions"
                  class="size-5 shrink-0 text-gray-400"
                  aria-hidden="true"
                />
                <Search
                  v-else
                  class="size-5 shrink-0 text-gray-400"
                  aria-hidden="true"
                />
                <span
                  v-for="command in stack"
                  :key="command.id"
                  class="inline-flex shrink-0 items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-gray-700"
                >
                  {{ command.chip ?? command.label.split(' ')[0] }}
                  <ChevronRight class="size-3" aria-hidden="true" />
                </span>
                <!-- ComboboxInput has no v-model; keyed by level so it remounts empty. -->
                <ComboboxInput
                  :key="stack.length"
                  v-focus
                  class="h-12 w-full border-0 bg-transparent px-0 text-sm text-black placeholder:text-gray-400 focus:ring-0 focus:outline-hidden dark:text-gray-100"
                  :placeholder="
                    current ? 'Choose an action…' : 'Search pages and tickets…'
                  "
                  aria-label="Command palette"
                  @change="query = ($event.target as HTMLInputElement).value"
                  @keydown.backspace="goBack"
                />
              </div>

              <ComboboxOptions
                v-if="commands.length > 0"
                static
                class="max-h-80 overflow-y-auto p-1"
              >
                <template v-for="[group, items] in groups" :key="group">
                  <li
                    class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 dark:text-gray-400"
                    role="presentation"
                  >
                    {{ group }}
                  </li>
                  <ComboboxOption
                    v-for="command in items"
                    :key="command.id"
                    v-slot="{ active }"
                    :value="command"
                    as="template"
                  >
                    <li
                      :class="[
                        active
                          ? 'bg-gray-500 text-white'
                          : 'text-black dark:text-gray-100',
                        'flex cursor-pointer items-center justify-between gap-3 rounded-sm px-4 py-2 text-sm',
                      ]"
                    >
                      <span class="truncate">{{ command.label }}</span>
                      <StatusPill
                        v-if="command.ticket"
                        :status="command.ticket.status"
                        :label="command.ticket.status_label"
                      />
                    </li>
                  </ComboboxOption>
                </template>
              </ComboboxOptions>
              <div
                v-else-if="searching || loadingActions"
                class="flex justify-center py-6"
              >
                <Spinner class="size-5 text-gray-400" aria-hidden="true" />
                <span class="sr-only">Searching…</span>
              </div>
              <p
                v-else
                class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
              >
                Nothing found.
              </p>
            </Combobox>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

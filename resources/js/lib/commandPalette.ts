import { ref } from 'vue';
import type { Component } from 'vue';

/** Whether the command palette is open; the nav button and `Cmd+K` both set it. */
export const paletteOpen = ref(false);

/**
 * A command either runs, or opens a nested level of commands (`children`, given the current
 * query for free-text rows). The palette keeps a stack of the commands that were opened, so
 * Backspace on an empty query goes back one level.
 */
export type Command = {
  id: string;
  label: string;
  group: string;
  /** Shown in the input's breadcrumb; defaults to the label's first word. */
  chip?: string;
  icon?: Component;
  ticket?: App.Data.Tickets.TicketData;
  run?: () => void;
  children?: (query: string) => Command[];
};

/** The tickets the palette's ticket actions apply to. `ticket` is set for a single one. */
export type PaletteTarget = {
  ticketIds: string[];
  ticket?: App.Data.Tickets.TicketData;
  /** Called after a bulk change succeeds, e.g. to clear the page's selection. */
  done?: () => void;
};

/** A page registers this to scope `Cmd+K` to its focused or selected tickets. */
export const paletteTarget = ref<(() => PaletteTarget | null) | null>(null);

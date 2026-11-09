import { router } from '@inertiajs/vue3';
import { onKeyStroke, useActiveElement } from '@vueuse/core';
import { nextTick, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { show } from '@/routes/tickets';

/**
 * Keyboard navigation for the ticket table: `j`/`k` or the arrows move the focused row,
 * `x` toggles its selection (`Shift+x` selects from the last toggled row), `Enter` or
 * `o` opens it, `Space` calls `onPreview`, and `Esc` clears the focus. Keys are ignored
 * while typing, inside a dialog or an open menu, or with Cmd/Ctrl/Alt held. Rows must carry a
 * `data-ticket-id` attribute so the focused one can be scrolled into view.
 */
export function useRowNavigation(options: {
  ids: () => string[];
  focusedId: Ref<string | undefined>;
  /** Leave unset (or return false from `canSelect`) to disable selection. */
  selected?: Ref<string[]>;
  canSelect?: () => boolean;
  onPreview?: (id: string) => void;
}): void {
  const active = useActiveElement();
  const anchorId = ref<string>();

  watch(
    () => options.ids(),
    (ids) => {
      if (options.focusedId.value && !ids.includes(options.focusedId.value)) {
        options.focusedId.value = undefined;
      }
    },
  );

  function focus(id: string): void {
    options.focusedId.value = id;
    void nextTick(() =>
      document
        .querySelector(`[data-ticket-id="${id}"]`)
        ?.scrollIntoView({ block: 'nearest' }),
    );
  }

  function move(step: 1 | -1): void {
    const ids = options.ids();
    const current = ids.indexOf(options.focusedId.value ?? '');
    const next =
      current === -1 ? (step === 1 ? 0 : ids.length - 1) : current + step;

    if (ids[next]) {
      focus(ids[next]);
    }
  }

  function toggle(id: string, range: boolean): void {
    const selected = options.selected;

    if (!selected || options.canSelect?.() === false) {
      return;
    }

    const ids = options.ids();
    const from = ids.indexOf(anchorId.value ?? '');

    if (range && from !== -1) {
      const to = ids.indexOf(id);
      const between = ids.slice(Math.min(from, to), Math.max(from, to) + 1);

      selected.value = [...new Set([...selected.value, ...between])];
    } else {
      selected.value = selected.value.includes(id)
        ? selected.value.filter((selectedId) => selectedId !== id)
        : [...selected.value, id];
    }

    anchorId.value = id;
  }

  onKeyStroke((event) => {
    const element = active.value;

    if (
      event.metaKey ||
      event.ctrlKey ||
      event.altKey ||
      element?.closest(
        'input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="dialog"], [role="menu"], [role="listbox"]',
      )
    ) {
      return;
    }

    const id = options.focusedId.value;
    const onControl = !!element?.closest('a, button');

    switch (event.key) {
      case 'j':
      case 'ArrowDown':
        event.preventDefault();
        move(1);
        break;
      case 'k':
      case 'ArrowUp':
        event.preventDefault();
        move(-1);
        break;
      case 'x':
      case 'X':
        if (id) {
          toggle(id, event.shiftKey);
        }
        break;
      case 'Enter':
      case 'o':
        // A focused link or button handles Enter itself.
        if (id && !(event.key === 'Enter' && onControl)) {
          router.visit(show(id));
        }
        break;
      case ' ':
        if (id && options.onPreview && !onControl) {
          event.preventDefault();
          options.onPreview(id);
        }
        break;
      case 'Escape':
        options.focusedId.value = undefined;
        break;
    }
  });
}

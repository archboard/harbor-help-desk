import { useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * The pages of a ticket thread before the newest one, which the page props carry. They load
 * through `fetchPage` on demand and stay when the props reload. Switching ticket forgets them.
 *
 * @param ticketId The ticket whose thread this is.
 * @param propsCursor The `earlier_before` prop: where the page props' own page ends.
 * @param pageUrl The URL of the page before `before`.
 */
export function useEarlierThread(
  ticketId: () => string,
  propsCursor: () => string | null,
  pageUrl: (before: string) => string,
): {
  comments: Ref<App.Data.Tickets.TicketCommentData[]>;
  events: Ref<App.Data.Tickets.TicketEventData[]>;
  hasEarlier: Ref<boolean>;
  loading: Ref<boolean>;
  failed: Ref<boolean>;
  loadEarlier: () => Promise<void>;
} {
  const http = useHttp({});
  const comments = ref<App.Data.Tickets.TicketCommentData[]>([]);
  const events = ref<App.Data.Tickets.TicketEventData[]>([]);
  /** Undefined until the first earlier page loads: then the props' cursor applies. */
  const loadedCursor = ref<string | null | undefined>(undefined);
  const failed = ref(false);

  const cursor = computed(() =>
    loadedCursor.value === undefined ? propsCursor() : loadedCursor.value,
  );

  watch(ticketId, () => {
    comments.value = [];
    events.value = [];
    loadedCursor.value = undefined;
    failed.value = false;
  });

  async function loadEarlier(): Promise<void> {
    if (cursor.value === null || http.processing) {
      return;
    }

    failed.value = false;

    try {
      const page = (await http.get(
        pageUrl(cursor.value),
      )) as App.Data.Tickets.TicketThreadData;

      comments.value = [...page.comments, ...comments.value];
      events.value = [...page.events, ...events.value];
      loadedCursor.value = page.earlier_before;
    } catch {
      failed.value = true;
    }
  }

  return {
    comments,
    events,
    hasEarlier: computed(() => cursor.value !== null),
    loading: computed(() => http.processing),
    failed,
    loadEarlier,
  };
}

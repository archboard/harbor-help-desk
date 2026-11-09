import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** A reply to prefill in the quick view's composer, e.g. a macro's text. The composer clears it. */
export const quickViewDraft = ref<{
  ticketId: string;
  body: string;
  isInternal: boolean;
} | null>(null);

/** Opens the quick view by loading the `preview` shared prop for the ticket. */
export function openQuickView(
  id: string,
  draft?: { body: string; isInternal: boolean },
): void {
  quickViewDraft.value = draft ? { ticketId: id, ...draft } : null;
  router.reload({ only: ['preview'], data: { preview: id } });
}

/** Closes the quick view by dropping `?preview=` from the current URL. */
export function closeQuickView(): void {
  const url = new URL(window.location.href);
  url.searchParams.delete('preview');

  router.get(
    url.pathname + url.search,
    {},
    {
      only: ['preview'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  );
}

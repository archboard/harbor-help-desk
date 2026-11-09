import { router } from '@inertiajs/vue3';

/** Opens the quick view by loading the `preview` shared prop for the ticket. */
export function openQuickView(id: string): void {
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

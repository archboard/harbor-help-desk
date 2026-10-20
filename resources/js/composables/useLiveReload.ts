import { router, usePage } from '@inertiajs/vue3';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type TicketChangedPayload = { actor_id: string | null };

/**
 * Reload the given Inertia props when a `TicketChanged` ping arrives on any of the
 * private channels, and once more when the socket reconnects after a drop. A reload
 * keeps page state and scroll. Pings for the current user's own changes are ignored,
 * because their request already refreshed the page.
 */
export function useLiveReload(options: {
  channels: string[];
  only: string[];
  reset?: string[];
}): void {
  const page = usePage();

  const reload = useDebounceFn(() => {
    router.reload({
      only: options.only,
      reset: options.reset,
    });
  }, 300);

  for (const channel of options.channels) {
    useEcho<TicketChangedPayload>(channel, '.TicketChanged', (payload) => {
      if (payload.actor_id !== page.props.auth.user?.id) {
        void reload();
      }
    });
  }

  const status = useConnectionStatus();
  let dropped = false;

  watch(status, (current) => {
    if (current === 'connected') {
      if (dropped) {
        dropped = false;
        void reload();
      }
    } else if (current !== 'connecting') {
      dropped = true;
    }
  });
}

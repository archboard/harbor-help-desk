import { router } from '@inertiajs/vue3';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

/**
 * Reload the given Inertia props when a ping (`TicketChanged` unless `event` says otherwise) arrives on any of the
 * private channels, and once more when the socket reconnects after a drop. A reload
 * keeps page state and scroll. The window that made the change never gets the ping,
 * because the server excludes its socket id (see `lib/realtime.ts`).
 */
export function useLiveReload(options: {
  channels: string[];
  /** The broadcast name of the ping, with its leading dot. */
  event?: string;
  only: string[];
  reset?: string[];
}): void {
  const reload = useDebounceFn(() => {
    router.reload({
      only: options.only,
      reset: options.reset,
    });
  }, 300);

  for (const channel of options.channels) {
    useEcho(channel, options.event ?? '.TicketChanged', () => void reload());
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

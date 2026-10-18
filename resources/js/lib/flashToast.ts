import { router } from '@inertiajs/vue3';
import { notify } from '@/lib/notifications';
import type { FlashToast } from '@/types/ui';

const levels = {
  success: 'success',
  info: 'neutral',
  warning: 'warning',
  error: 'error',
} as const;

export function initializeFlashToast(): void {
  router.on('flash', (event) => {
    const flash = (event as CustomEvent).detail?.flash;
    const data = flash?.toast as FlashToast | undefined;

    if (!data) {
      return;
    }

    notify({ level: levels[data.type], text: data.message });
  });
}

import { configureEcho, echo } from '@laravel/echo-vue';
import { http, router } from '@inertiajs/vue3';
import type { Page } from '@inertiajs/core';

let configured = false;
let listening = false;

/**
 * Configure Echo once, from the first page that carries the realtime settings. Guest pages
 * don't have them, so the socket is only set up after the first authenticated visit.
 */
export function initializeRealtime(page: Page): void {
  if (!listening) {
    listening = true;
    router.on('navigate', (event) => initializeRealtime(event.detail.page));
  }

  const realtime = (page.props as { realtime?: App.Data.Realtime.RealtimeData })
    .realtime;

  if (configured || !realtime) {
    return;
  }

  configured = true;

  configureEcho({
    broadcaster: 'reverb',
    key: realtime.key,
    wsHost: realtime.host,
    wsPort: realtime.port,
    wssPort: realtime.port,
    forceTLS: realtime.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  });

  // Inertia v3 has no Axios, so Echo can't add this itself. The server uses it to skip
  // broadcasting to the window that made the change.
  http.onRequest((config) => {
    const socketId = echo().socketId();

    return socketId
      ? { ...config, headers: { ...config.headers, 'X-Socket-ID': socketId } }
      : config;
  });
}

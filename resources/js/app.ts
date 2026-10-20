import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import WorkspaceLayout from '@/layouts/WorkspaceLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeRealtime } from '@/lib/realtime';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    if (name.startsWith('auth/')) {
      return AuthLayout;
    }

    return name.startsWith('workspace/')
      ? [AppLayout, WorkspaceLayout]
      : AppLayout;
  },
  withApp: (app, { page }) => {
    initializeRealtime(page);

    app.directive('focus', {
      mounted: (el: HTMLElement, shouldFocus) => {
        if (shouldFocus.value !== false) {
          el.focus();
        }
      },
    });
  },
  progress: {
    color: '#2563eb',
  },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

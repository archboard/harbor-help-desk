import { createInertiaApp, router } from '@inertiajs/vue3';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import I18nT from '@/components/I18nT';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import WorkspaceLayout from '@/layouts/WorkspaceLayout.vue';
import { setDateLocale } from '@/lib/dates';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeRealtime } from '@/lib/realtime';

// English is the source text, so it has no file. Other locales are `lang/{locale}.json`.
const translations = import.meta.glob<{ default: Record<string, string> }>(
  '../../lang/*.json',
);

/**
 * Load a locale's strings, and its dayjs locale so dates follow.
 */
function resolveTranslations(locale: string) {
  void setDateLocale(locale);

  return (
    translations[`../../lang/${locale}.json`]?.() ??
    Promise.resolve({ default: {} })
  );
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    if (name.startsWith('auth/') || name.startsWith('install/')) {
      return AuthLayout;
    }

    if (name.startsWith('portal/')) {
      return PortalLayout;
    }

    return name.startsWith('workspace/')
      ? [AppLayout, WorkspaceLayout]
      : AppLayout;
  },
  withApp: (app, { page }) => {
    initializeRealtime(page);

    app.use(i18nVue, {
      lang: page.props.locale,
      fallbackLang: 'en',
      resolve: resolveTranslations,
    });

    // Follow the locale when the user changes it.
    router.on('navigate', (event) => {
      void loadLanguageAsync(event.detail.page.props.locale);
    });

    app.component('I18nT', I18nT);

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

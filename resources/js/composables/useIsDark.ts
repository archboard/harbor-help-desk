import { createSharedComposable, useMutationObserver } from '@vueuse/core';
import { ref } from 'vue';

/**
 * Whether the app is showing its dark appearance right now. Follows the `dark` class
 * `useAppearance` toggles on `<html>`, so it tracks the user's choice, not just the OS.
 */
export const useIsDark = createSharedComposable(() => {
  const root = document.documentElement;
  const isDark = ref(root.classList.contains('dark'));

  useMutationObserver(
    root,
    () => {
      isDark.value = root.classList.contains('dark');
    },
    { attributeFilter: ['class'] },
  );

  return isDark;
});

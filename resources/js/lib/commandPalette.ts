import { ref } from 'vue';

/** Whether the command palette is open; the nav button and `Cmd+K` both set it. */
export const paletteOpen = ref(false);

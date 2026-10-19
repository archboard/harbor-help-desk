<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import FadeIn from '@/components/transitions/FadeIn.vue';
import { guessTimezone } from '@/lib/dates';
import { update as updateTimezone } from '@/routes/profile/timezone';

const page = usePage();
const browserTimezone = guessTimezone();

/**
 * Remembered per browser timezone, so travelling somewhere new asks again. "Keep" is stored
 * as `forever`; "Not now" stores when to ask again.
 */
const dismissedKey = `timezone-prompt-dismissed:${browserTimezone}`;
const NOT_NOW_DAYS = 7;

function wasDismissed(): boolean {
  try {
    const until = localStorage.getItem(dismissedKey);

    return until === 'forever' || (until !== null && dayjs().isBefore(until));
  } catch {
    return false;
  }
}

const dismissed = ref(wasDismissed());
const saving = ref(false);

const savedTimezone = computed(() => page.props.auth.user.timezone);
const show = computed(
  () => !dismissed.value && savedTimezone.value !== browserTimezone,
);

function useBrowserTimezone(): void {
  router.put(
    updateTimezone().url,
    { timezone: browserTimezone },
    {
      preserveScroll: true,
      onStart: () => (saving.value = true),
      onFinish: () => (saving.value = false),
    },
  );
}

function dismiss(): void {
  dismissed.value = true;

  try {
    localStorage.setItem(
      dismissedKey,
      savedTimezone.value
        ? 'forever'
        : dayjs().add(NOT_NOW_DAYS, 'day').toISOString(),
    );
  } catch {
    // Without storage the prompt simply comes back on the next full page load.
  }
}
</script>

<template>
  <FadeIn>
    <div
      v-if="show"
      class="mb-6 flex flex-col gap-3 rounded-lg bg-primary-50 p-4 text-sm text-primary-900 sm:flex-row sm:items-center dark:bg-primary-950 dark:text-primary-100"
      role="status"
    >
      <p class="flex-1">
        <template v-if="savedTimezone">
          Your device is set to <strong>{{ browserTimezone }}</strong
          >, but your account uses <strong>{{ savedTimezone }}</strong
          >. Switch to your device's timezone?
        </template>
        <template v-else>
          Use <strong>{{ browserTimezone }}</strong> as your timezone?
        </template>
      </p>
      <div class="flex shrink-0 gap-2">
        <AppButton
          type="button"
          size="sm"
          :loading="saving"
          @click="useBrowserTimezone"
        >
          Use {{ browserTimezone }}
        </AppButton>
        <AppButton type="button" size="sm" color="white" @click="dismiss">
          {{ savedTimezone ? `Keep ${savedTimezone}` : 'Not now' }}
        </AppButton>
      </div>
    </div>
  </FadeIn>
</template>

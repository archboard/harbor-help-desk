<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Circle, CircleCheck } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import { create } from '@/routes/tickets';
import { show } from '@/routes/desks';
import { index as emailIndex } from '@/routes/workspace/email';
import { index as membersIndex } from '@/routes/workspace/members';
import { update } from '@/routes/workspace/settings';

const { onboarding } = defineProps<{
  onboarding: App.Data.Workspaces.OnboardingData;
}>();

const dismissed = ref(false);

/** Steps marked done here, shown as done before the reload confirms it. */
const markedDone = ref<string[]>([]);

const steps = computed(() =>
  onboarding.steps.map((step) => ({
    ...step,
    complete: step.complete || markedDone.value.includes(step.key),
  })),
);

const done = computed(() => steps.value.filter((step) => step.complete).length);

/** Literal `$t()` calls per step, so translation tooling finds every string. */
const copy = computed<
  Record<string, { label: string; description: string; href?: string }>
>(() => ({
  email: {
    label: trans('Connect your email'),
    description: trans('Receive customer emails as tickets.'),
    href: emailIndex.url(),
  },
  team: {
    label: trans('Invite your team'),
    description: trans("Add the people who'll answer tickets."),
    href: membersIndex.url(),
  },
  desk: {
    label: trans('Set up your desk'),
    description: trans('Choose how tickets are assigned and pick an SLA.'),
    href: onboarding.default_desk_id
      ? show.url(onboarding.default_desk_id)
      : undefined,
  },
  first_ticket: {
    label: trans('Receive your first ticket'),
    description: onboarding.inbound_address
      ? trans('Send an email to :address to try it.', {
          address: onboarding.inbound_address,
        })
      : trans('Create a ticket to see how it works.'),
    href: create.url(),
  },
}));

function markDone(key: string): void {
  markedDone.value.push(key);
  router.patch(
    update.url(),
    {
      onboarding_skipped_steps: [
        ...onboarding.steps
          .filter((step) => step.skipped)
          .map((step) => step.key),
        key,
      ],
    },
    {
      preserveScroll: true,
      onError: () =>
        (markedDone.value = markedDone.value.filter((k) => k !== key)),
    },
  );
}

function dismiss(): void {
  dismissed.value = true;
  router.patch(
    update.url(),
    { onboarding_dismissed: true },
    { preserveScroll: true, onError: () => (dismissed.value = false) },
  );
}
</script>

<template>
  <CardWrapper v-if="!dismissed">
    <CardPadding>
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-base font-medium text-gray-900 dark:text-white">
            {{ $t('Get set up') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{
              $t(':done of :total done', {
                done: String(done),
                total: String(onboarding.steps.length),
              })
            }}
          </p>
        </div>
        <button
          type="button"
          class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          @click="dismiss"
        >
          {{ $t('Dismiss') }}
        </button>
      </div>
      <div
        class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
        role="progressbar"
        :aria-valuenow="done"
        aria-valuemin="0"
        :aria-valuemax="onboarding.steps.length"
      >
        <div
          class="h-full rounded-full bg-primary-600 transition-all"
          :style="{ width: `${(done / onboarding.steps.length) * 100}%` }"
        />
      </div>
      <ul class="mt-4 space-y-3">
        <li
          v-for="step in steps"
          :key="step.key"
          class="flex items-start gap-3"
        >
          <component
            :is="step.complete ? CircleCheck : Circle"
            :class="[
              'mt-0.5 size-5 shrink-0',
              step.complete
                ? 'text-green-600 dark:text-green-400'
                : 'text-gray-300 dark:text-gray-600',
            ]"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <component
              :is="copy[step.key]?.href ? Link : 'span'"
              :href="copy[step.key]?.href"
              :class="[
                'text-sm font-medium',
                step.complete
                  ? 'text-gray-400 line-through dark:text-gray-500'
                  : 'text-gray-900 dark:text-white',
                copy[step.key]?.href && 'hover:underline',
              ]"
            >
              {{ copy[step.key]?.label }}
            </component>
            <p
              :class="[
                'text-sm break-words',
                step.complete
                  ? 'text-gray-400 dark:text-gray-500'
                  : 'text-gray-500 dark:text-gray-400',
              ]"
            >
              {{ copy[step.key]?.description }}
            </p>
          </div>
          <button
            v-if="!step.complete"
            type="button"
            class="shrink-0 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            @click="markDone(step.key)"
          >
            {{ $t('Mark as done') }}
          </button>
        </li>
      </ul>
    </CardPadding>
  </CardWrapper>
</template>

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoNotification } from '@laravel/echo-vue';
import { Bell } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ScaleIn from '@/components/transitions/ScaleIn.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { index, read, readOne } from '@/routes/notifications';
import { show } from '@/routes/tickets';

type Notification = App.Data.Notifications.NotificationData;

const page = usePage();
const http = useHttp({});

const unreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const notifications = ref<Notification[]>([]);
let loaded = false;

async function load(): Promise<void> {
  const response = (await http.get(index.url())) as { data: Notification[] };

  notifications.value = response.data;
  loaded = true;
}

function refresh(): void {
  router.reload({ only: ['notifications'] });

  if (loaded) {
    void load();
  }
}

/**
 * The sentence for a notification. Ticket notifications send a `description`.
 */
function sentence(notification: Notification): string {
  const { description, note } = notification.data;

  if (typeof description === 'string') {
    return description;
  }

  if (notification.type === 'reminder_due') {
    return typeof note === 'string' && note
      ? trans('Reminder: :note', { note })
      : trans('Reminder due');
  }

  return trans('New notification');
}

async function open(
  notification: Notification,
  close: () => void,
): Promise<void> {
  close();

  if (!notification.read_at) {
    await http.post(readOne.url(notification.id));
  }

  router.visit(show.url({ ticket: String(notification.data.ticket_id) }));
}

async function markAllRead(): Promise<void> {
  await http.post(read.url());
  refresh();
  await load();
}

// A broadcast carries no data, only the news that something arrived.
useEchoNotification(`users.${page.props.auth.user.id}`, refresh);

const status = useConnectionStatus();
let dropped = false;

watch(status, (current) => {
  if (current === 'connected') {
    if (dropped) {
      dropped = false;
      refresh();
    }
  } else if (current !== 'connecting') {
    dropped = true;
  }
});
</script>

<template>
  <Popover class="relative">
    <PopoverButton
      class="relative rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:text-gray-300 dark:hover:bg-gray-800"
      @click="load()"
    >
      <span class="sr-only">{{ $t('Notifications') }}</span>
      <Bell class="size-5" aria-hidden="true" />
      <span
        v-if="unreadCount > 0"
        class="absolute top-0.5 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary-600 px-1 text-[10px] leading-4 font-semibold text-white"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </PopoverButton>

    <ScaleIn>
      <PopoverPanel
        v-slot="{ close }"
        class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
      >
        <div
          class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700"
        >
          <h2 class="text-sm font-semibold">{{ $t('Notifications') }}</h2>
          <button
            v-if="unreadCount > 0"
            type="button"
            class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
            @click="markAllRead()"
          >
            {{ $t('Mark all read') }}
          </button>
        </div>

        <p
          v-if="notifications.length === 0"
          class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
        >
          {{ $t('Nothing yet.') }}
        </p>

        <ul
          v-else
          class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700"
        >
          <li v-for="notification in notifications" :key="notification.id">
            <button
              type="button"
              class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-hidden dark:hover:bg-gray-700 dark:focus:bg-gray-700"
              @click="open(notification, close)"
            >
              <span
                class="mt-1.5 size-2 shrink-0 rounded-full"
                :class="
                  notification.read_at ? 'bg-transparent' : 'bg-primary-600'
                "
                aria-hidden="true"
              />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm">
                  {{ sentence(notification) }}
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                  #{{ notification.data.ticket_number }} ·
                  <RelativeTime :datetime="notification.created_at" />
                </span>
              </span>
            </button>
          </li>
        </ul>
      </PopoverPanel>
    </ScaleIn>
  </Popover>
</template>

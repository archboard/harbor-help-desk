<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CardWrapper from '@/components/CardWrapper.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { show as showTicket } from '@/routes/tickets';
import { index } from '@/routes/workspace/audit-log';

defineOptions({ layout: { title: 'Audit log' } });

const props = defineProps<{
  tab: 'admin' | 'tickets';
  events: {
    data: (App.Data.Audit.AuditEventData | App.Data.Tickets.TicketEventData)[];
  };
  actions: Record<App.Enums.AuditAction, string>;
  ticketEventTypes: App.Enums.TicketEventType[];
  members: App.Data.Users.UserData[];
}>();

const params = new URLSearchParams(window.location.search);
const type = ref(params.get(props.tab === 'admin' ? 'action' : 'type') ?? '');
const actorId = ref(params.get('actor_id') ?? '');
const from = ref(params.get('from') ?? '');
const to = ref(params.get('to') ?? '');

watch(
  () => props.tab,
  () => {
    type.value = actorId.value = from.value = to.value = '';
  },
);

const tabs = [
  { key: 'admin', label: 'Admin activity' },
  { key: 'tickets', label: 'Ticket activity' },
] as const;

const typeOptions = computed(() =>
  props.tab === 'admin'
    ? Object.entries(props.actions).map(([value, label]) => ({ value, label }))
    : props.ticketEventTypes.map((value) => ({
        value,
        label: value.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase()),
      })),
);

function filters(): Record<string, string> {
  const query: Record<string, string> = { tab: props.tab };
  const values = {
    [props.tab === 'admin' ? 'action' : 'type']: type.value,
    actor_id: actorId.value,
    from: from.value,
    to: to.value,
  };

  for (const [key, value] of Object.entries(values)) {
    if (value) {
      query[key] = value;
    }
  }

  return query;
}

function apply(): void {
  if (from.value && to.value && from.value > to.value) {
    return;
  }

  router.get(index().url, filters(), { preserveState: true, replace: true });
}

const field =
  'rounded-md border-gray-300 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900';
</script>

<template>
  <div class="space-y-6">
    <Head title="Audit log" />

    <nav
      class="flex gap-6 border-b border-gray-200 dark:border-gray-800"
      aria-label="Audit log"
    >
      <Link
        v-for="item in tabs"
        :key="item.key"
        :href="index({ query: { tab: item.key } })"
        class="-mb-px border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap"
        :class="
          item.key === tab
            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
        "
        :aria-current="item.key === tab ? 'page' : undefined"
      >
        {{ item.label }}
      </Link>
    </nav>

    <div class="flex flex-wrap items-center gap-3">
      <select
        v-model="type"
        :aria-label="tab === 'admin' ? 'Action' : 'Event type'"
        :class="field"
        @change="apply"
      >
        <option value="">
          {{ tab === 'admin' ? 'All actions' : 'All event types' }}
        </option>
        <option
          v-for="option in typeOptions"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>

      <select
        v-model="actorId"
        aria-label="Actor"
        :class="field"
        @change="apply"
      >
        <option value="">Everyone</option>
        <option v-for="member in members" :key="member.id" :value="member.id">
          {{ member.name }}
        </option>
      </select>

      <div class="flex items-center gap-2 text-sm">
        <input
          v-model="from"
          type="date"
          aria-label="From"
          :max="to || undefined"
          :class="field"
          @change="apply"
        />
        <span class="text-gray-500">to</span>
        <input
          v-model="to"
          type="date"
          aria-label="To"
          :min="from || undefined"
          :class="field"
          @change="apply"
        />
      </div>
    </div>

    <CardWrapper>
      <p
        v-if="events.data.length === 0"
        class="p-6 text-sm text-gray-500 dark:text-gray-400"
      >
        Nothing has happened yet.
      </p>

      <InfiniteScroll v-else data="events" as="ul" class="divide-y">
        <li
          v-for="event in events.data"
          :key="event.id"
          class="flex items-start justify-between gap-4 px-6 py-3 text-sm"
        >
          <div class="min-w-0">
            <p class="text-gray-900 dark:text-gray-100">
              <template v-if="'action' in event">
                <span class="font-medium">{{ event.actor_name }}</span>
                &middot; {{ event.description }}
              </template>
              <template v-else>
                <Link
                  v-if="event.ticket_id"
                  :href="showTicket(event.ticket_id)"
                  class="font-medium text-primary-600 dark:text-primary-400"
                >
                  #{{ event.ticket_number ?? '' }}
                </Link>
                {{ event.description }}
              </template>
            </p>
            <p
              v-if="'action' in event && event.ip"
              class="text-xs text-gray-500 dark:text-gray-400"
            >
              {{ event.ip }}
            </p>
          </div>
          <RelativeTime
            :datetime="event.created_at"
            class="shrink-0 text-gray-500 dark:text-gray-400"
          />
        </li>
      </InfiniteScroll>
    </CardWrapper>
  </div>
</template>

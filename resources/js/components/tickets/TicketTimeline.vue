<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { computed } from 'vue';
import Avatar from '@/components/Avatar.vue';
import ReplyComposer from '@/components/tickets/ReplyComposer.vue';
import TicketCommentCard from '@/components/tickets/TicketCommentCard.vue';
import { formatDateTime, timeAgo } from '@/lib/dates';

const { comments, events, canWork } = defineProps<{
  ticketId: string;
  deskMembers: App.Data.Desks.DeskMemberData[];
  comments: App.Data.Tickets.TicketCommentData[];
  events: App.Data.Tickets.TicketEventData[];
  /** Can reply and edit their own comments: may update and the ticket isn't deleted. */
  canWork: boolean;
  /** Can delete anyone's comment, like the ticket itself. */
  canDeleteAny: boolean;
}>();

const page = usePage();

/** Comments and history in the order they happened. */
const items = computed(() =>
  [
    ...comments.map((comment) => ({
      key: comment.id,
      at: comment.created_at ?? '',
      comment,
    })),
    ...events.map((event) => ({ key: event.id, at: event.created_at, event })),
  ].sort((a, b) => a.at.localeCompare(b.at)),
);

/** Mirrors `TicketCommentPolicy`: authors edit their own comments on tickets they can work. */
function isOwnComment(comment: App.Data.Tickets.TicketCommentData): boolean {
  return (
    canWork &&
    comment.author_type === 'user' &&
    comment.author_id === page.props.auth.user.id
  );
}

/** Split "Jane Doe replied." so the name can stand out. */
function sentence(event: App.Data.Tickets.TicketEventData): {
  actor: string;
  rest: string;
} {
  return event.description.startsWith(event.actor_name)
    ? {
        actor: event.actor_name,
        rest: event.description.slice(event.actor_name.length),
      }
    : { actor: '', rest: event.description };
}

/** Solving or closing a ticket gets a check instead of a plain dot. */
function isResolution(event: App.Data.Tickets.TicketEventData): boolean {
  return (
    event.type === 'status_changed' &&
    ['solved', 'closed'].includes(String(event.properties?.to))
  );
}
</script>

<template>
  <div>
    <ul role="list" class="space-y-6">
      <li
        v-for="(item, index) in items"
        :key="item.key"
        class="relative flex gap-x-4"
      >
        <div
          :class="[
            index === items.length - 1 ? 'h-6' : '-bottom-6',
            'absolute top-0 left-0 flex w-6 justify-center',
          ]"
          aria-hidden="true"
        >
          <div class="w-px bg-gray-200 dark:bg-gray-800" />
        </div>

        <template v-if="'comment' in item">
          <Avatar :name="item.comment.author_name" class="relative mt-3" />
          <TicketCommentCard
            class="flex-auto"
            :comment="item.comment"
            :can-edit="isOwnComment(item.comment)"
            :can-delete="isOwnComment(item.comment) || canDeleteAny"
          />
        </template>

        <template v-else>
          <div
            class="relative flex size-6 flex-none items-center justify-center bg-gray-50 dark:bg-black"
          >
            <CircleCheck
              v-if="isResolution(item.event)"
              class="size-6 text-primary-600 dark:text-primary-400"
              aria-hidden="true"
            />
            <div
              v-else
              class="size-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300 dark:bg-gray-800 dark:ring-gray-600"
            />
          </div>
          <p
            class="flex-auto py-0.5 text-xs/5 text-gray-500 dark:text-gray-400"
          >
            <span class="font-medium text-gray-900 dark:text-gray-100">
              {{ sentence(item.event).actor }}
            </span>
            {{ sentence(item.event).rest }}
          </p>
          <time
            :datetime="item.event.created_at"
            :title="formatDateTime(item.event.created_at)"
            class="flex-none py-0.5 text-xs/5 text-gray-500 dark:text-gray-400"
          >
            {{ timeAgo(item.event.created_at) }}
          </time>
        </template>
      </li>
    </ul>

    <div v-if="canWork" class="mt-6 flex gap-x-3">
      <Avatar :name="page.props.auth.user.name" class="mt-1" />
      <ReplyComposer
        class="flex-auto"
        :ticket-id="ticketId"
        :desk-members="deskMembers"
      />
    </div>
  </div>
</template>

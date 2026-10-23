import {
  CalendarClock,
  CirclePlus,
  Clock,
  MessageSquare,
  Pencil,
  Webhook,
  Zap,
} from '@lucide/vue';

const icons = {
  'ticket.created': CirclePlus,
  'ticket.comment_added': MessageSquare,
  'ticket.field_changed': Pencil,
  'ticket.sla': Clock,
  schedule: CalendarClock,
  webhook: Webhook,
};

/** The icon for a trigger type. */
export const triggerIcon = (type: string) =>
  icons[type as keyof typeof icons] ?? Zap;

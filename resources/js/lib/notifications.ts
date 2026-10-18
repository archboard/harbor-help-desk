import { reactive } from 'vue';
import type { AlertLevel } from '@/types';

export type AppNotification = {
  id: number;
  level: AlertLevel;
  text: string;
  title?: string;
};

let nextId = 0;
const timeouts = new Map<number, ReturnType<typeof setTimeout>>();

export const notifications = reactive<AppNotification[]>([]);

export function notify(
  notification: Omit<AppNotification, 'id'>,
  delay = 4000,
): void {
  const id = ++nextId;

  notifications.unshift({ ...notification, id });
  timeouts.set(
    id,
    setTimeout(() => removeNotification(id), delay),
  );
}

export function removeNotification(id: number): void {
  clearTimeout(timeouts.get(id));
  timeouts.delete(id);

  const index = notifications.findIndex((n) => n.id === id);

  if (index !== -1) {
    notifications.splice(index, 1);
  }
}

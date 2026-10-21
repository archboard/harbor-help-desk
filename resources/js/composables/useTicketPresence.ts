import { usePage } from '@inertiajs/vue3';
import { useEchoPresence } from '@laravel/echo-vue';
import { useIntervalFn, useTimeoutFn, watchDebounced } from '@vueuse/core';
import { computed, onScopeDispose, ref, type Ref } from 'vue';

type Viewer = App.Data.Users.UserData;

/**
 * Who else is viewing the ticket, and who is typing a reply. Typing travels as a client
 * whisper between members; there is no server state. A whisper is repeated while `typing`
 * stays true, and each typist clears itself after 5s without one, in case a tab closes
 * mid-typing.
 */
export function useTicketPresence(ticketId: string, typing: Ref<boolean>) {
  const page = usePage();
  const members = ref<Viewer[]>([]);
  const typingUserIds = ref<string[]>([]);
  const expiries = new Map<string, ReturnType<typeof useTimeoutFn>>();

  const viewers = computed(() =>
    members.value.filter((member) => member.id !== page.props.auth.user.id),
  );

  function setTyping(userId: string, isTyping: boolean): void {
    expiries.get(userId)?.stop();
    typingUserIds.value = typingUserIds.value.filter((id) => id !== userId);

    if (!isTyping) {
      return;
    }

    typingUserIds.value.push(userId);
    const expiry = useTimeoutFn(() => setTyping(userId, false), 5000);
    expiries.set(userId, expiry);
  }

  const { channel } = useEchoPresence(`tickets.${ticketId}.presence`);

  channel()
    .here((users: Viewer[]) => (members.value = users))
    .joining((user: Viewer) => members.value.push(user))
    .leaving((user: Viewer) => {
      members.value = members.value.filter(({ id }) => id !== user.id);
      setTyping(user.id, false);
    })
    .listenForWhisper('typing', (event: { id: string; typing: boolean }) =>
      setTyping(event.id, event.typing),
    );

  function whisper(): void {
    channel().whisper('typing', {
      id: page.props.auth.user.id,
      typing: typing.value,
    });
  }

  watchDebounced(typing, whisper, { debounce: 300 });
  // ponytail: re-whisper every 3s so receivers' 5s expiry doesn't drop a long typing spell.
  useIntervalFn(() => typing.value && whisper(), 3000);

  onScopeDispose(() => expiries.forEach((expiry) => expiry.stop()));

  return { viewers, typingUserIds };
}

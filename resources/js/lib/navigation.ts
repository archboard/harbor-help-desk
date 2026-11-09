import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { edit as editApiTokens } from '@/routes/api-tokens';
import { index as automations } from '@/routes/automations';
import { edit as editAppearance } from '@/routes/appearance';
import { index as contacts } from '@/routes/contacts';
import { index as desks } from '@/routes/desks';
import { edit as editProfile } from '@/routes/profile';
import { index as reports } from '@/routes/reports';
import { edit as editSecurity } from '@/routes/security';
import { index as tickets } from '@/routes/tickets';
import { edit as workspaceSettings } from '@/routes/workspace/general';

/** The main and user-menu links, shared by the nav bar and the command palette. */
export function useNavigation() {
  const page = usePage();

  const navigation = computed(() => [
    { label: 'Tickets', href: tickets().url },
    { label: 'Contacts', href: contacts().url },
    { label: 'Desks', href: desks().url },
    { label: 'Reports', href: reports().url },
    ...(page.props.canViewAutomations
      ? [{ label: 'Automations', href: automations().url }]
      : []),
    ...(page.props.workspace?.is_admin
      ? [{ label: 'Workspace', href: workspaceSettings().url }]
      : []),
  ]);

  const userNavigation = computed(() => [
    { label: 'Profile', href: editProfile().url },
    { label: 'Security', href: editSecurity().url },
    { label: 'API tokens', href: editApiTokens().url },
    { label: 'Appearance', href: editAppearance().url },
  ]);

  return { navigation, userNavigation };
}

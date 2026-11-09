import { router } from '@inertiajs/vue3';
import type { Command, PaletteTarget } from '@/lib/commandPalette';
import { bulk, update } from '@/routes/tickets';

type Context = {
  target: PaletteTarget;
  /** Undefined until the shared `palette` prop has loaded. */
  palette?: App.Data.Palette.PaletteData;
  userId: string;
  applyMacro: (ticketId: string, macro: App.Data.Macros.MacroData) => void;
};

/**
 * The actions for a target's tickets. Status, priority, assignees, desk and tags work on
 * many tickets (`tickets.bulk`); custom fields and macros only on one.
 */
export function ticketCommands({
  target,
  palette,
  userId,
  applyMacro,
}: Context): Command[] {
  const { ticketIds, ticket } = target;
  const group = ticket
    ? `Ticket #${ticket.number}`
    : `${ticketIds.length} selected tickets`;

  function change(body: Record<string, string | string[]>): void {
    router.post(
      bulk().url,
      { ticket_ids: ticketIds, ...body },
      { preserveScroll: true, onSuccess: () => target.done?.() },
    );
  }

  function level(
    id: string,
    label: string,
    chip: string,
    children: (query: string) => Command[],
  ): Command {
    return { id: `ticket-${id}`, label, chip, group, children };
  }

  function choices(
    id: string,
    items: Array<[value: string, label: string]>,
    pick: (value: string) => void,
  ): Command[] {
    return items.map(([value, label]) => ({
      id: `${id}-${value}`,
      label,
      group: 'Choose',
      run: () => pick(value),
    }));
  }

  const commands: Command[] = [
    level('status', 'Set status', 'Status', () =>
      choices('status', Object.entries(palette?.statuses ?? {}), (status) =>
        change({ status }),
      ),
    ),
    level('priority', 'Set priority', 'Priority', () =>
      choices(
        'priority',
        Object.entries(palette?.priorities ?? {}),
        (priority) => change({ priority }),
      ),
    ),
    level('assign', 'Assign to', 'Assign', () =>
      choices(
        'assign',
        [
          [userId, 'Me'],
          ['none', 'Nobody'],
          ...(palette?.agents ?? [])
            .filter((agent) => agent.id !== userId)
            .map((agent): [string, string] => [agent.id, agent.name]),
        ],
        (id) => change({ assignee_ids: id === 'none' ? [] : [id] }),
      ),
    ),
    level('desk', 'Move to desk', 'Desk', () =>
      choices(
        'desk',
        (palette?.desks ?? [])
          .filter((desk) => desk.id !== ticket?.desk_id)
          .map((desk): [string, string] => [desk.id, desk.name]),
        (desk_id) => change({ desk_id }),
      ),
    ),
    level('tag', 'Add tag', 'Tag', (query) => {
      const name = query.trim();
      const tags = palette?.tags ?? [];
      const exists = tags.some(
        (tag) => tag.name.toLowerCase() === name.toLowerCase(),
      );

      return [
        ...choices(
          'tag',
          tags.map((tag): [string, string] => [tag.name, tag.name]),
          (add_tag) => change({ add_tag }),
        ),
        ...(name && !exists
          ? [
              {
                id: 'tag-new',
                label: `Create tag "${name}"`,
                group: 'Choose',
                run: () => change({ add_tag: name }),
              },
            ]
          : []),
      ];
    }),
  ];

  if (!ticket) {
    return commands;
  }

  const fields =
    palette?.desks.find((desk) => desk.id === ticket.desk_id)?.custom_fields ??
    [];

  function setField(
    id: string,
    value: string | number | boolean | string[],
  ): void {
    router.patch(
      update(ticket!.id).url,
      { custom_fields: { [id]: value } },
      { preserveScroll: true },
    );
  }

  if (fields.length > 0) {
    commands.push(
      level('field', 'Set field', 'Field', () =>
        fields.map((field) => ({
          id: `field-${field.id}`,
          label: field.name,
          chip: field.name,
          group: 'Fields',
          children: (query: string) => {
            if (field.type === 'checkbox') {
              return choices(
                `field-${field.id}`,
                [
                  ['yes', 'Yes'],
                  ['no', 'No'],
                ],
                (value) => setField(field.id, value === 'yes'),
              );
            }

            // A multi-select sets the one chosen value, replacing any others.
            if (field.type === 'select' || field.type === 'multi_select') {
              return choices(
                `field-${field.id}`,
                (field.options ?? []).map((option): [string, string] => [
                  option,
                  option,
                ]),
                (value) =>
                  setField(field.id, field.type === 'select' ? value : [value]),
              );
            }

            const value = query.trim();

            return value
              ? [
                  {
                    id: `field-${field.id}-value`,
                    label: `Set ${field.name} to "${value}"`,
                    group: 'Choose',
                    run: () =>
                      setField(
                        field.id,
                        field.type === 'number' || field.type === 'decimal'
                          ? Number(value)
                          : value,
                      ),
                  },
                ]
              : [];
          },
        })),
      ),
    );
  }

  const macros = (palette?.macros ?? []).filter(
    (macro) => macro.desk_id === null || macro.desk_id === ticket.desk_id,
  );

  if (macros.length > 0) {
    commands.push(
      level('macro', 'Run macro', 'Macro', () =>
        macros.map((macro) => ({
          id: `macro-${macro.id}`,
          label: macro.name,
          group: 'Macros',
          run: () => applyMacro(ticket.id, macro),
        })),
      ),
    );
  }

  return commands;
}

import { toRaw } from 'vue';

type Catalog = App.Data.Automations.AutomationCatalogData;
type Field = App.Data.Automations.CatalogFieldData;
export type Rule = App.Data.Automations.RuleData;
export type Group = Pick<
  App.Data.Automations.ConditionGroupData,
  'match' | 'rules'
>;
export type Node = Group['rules'][number];

/** The field groups every trigger offers; a trigger adds its own (the server's `ALWAYS_AVAILABLE`). */
const ALWAYS_AVAILABLE: App.Automations.FieldGroup[] = [
  'ticket',
  'requester',
  'time',
  'sla',
  'custom_fields',
];

export const MAX_DEPTH = 3;

export function availableGroups(
  catalog: Catalog,
  triggerType: string,
): App.Automations.FieldGroup[] {
  return [
    ...ALWAYS_AVAILABLE,
    ...(catalog.triggers.find((trigger) => trigger.key === triggerType)
      ?.field_groups ?? []),
  ];
}

const LIST_OPERATORS = [
  'in',
  'not_in',
  'includes_any',
  'includes_all',
  'excludes',
];

export const isChange = (operator: string): boolean =>
  operator.startsWith('changed');

/** The operators of a field's type, without the change ones when the trigger reports no change. */
export function operatorsFor(
  catalog: Catalog,
  field: Field | undefined,
  groups: App.Automations.FieldGroup[],
): App.Data.Automations.CatalogOperatorData[] {
  return (field ? (catalog.operators[field.type] ?? []) : []).filter(
    (operator) => groups.includes('event') || !isChange(operator.value),
  );
}

/** What kind of value an operator takes: none, one, or a list. */
export function valueShape(
  catalog: Catalog,
  field: Field | undefined,
  operator: string,
): 'none' | 'one' | 'list' {
  const takesValue = field
    ? catalog.operators[field.type]?.find((item) => item.value === operator)
        ?.takes_value
    : undefined;

  return takesValue === false
    ? 'none'
    : LIST_OPERATORS.includes(operator)
      ? 'list'
      : 'one';
}

/** The value a fresh input starts with. */
export function defaultValue(
  field: Field | undefined,
  shape: ReturnType<typeof valueShape>,
): unknown {
  if (shape === 'none') {
    return null;
  }

  if (shape === 'list') {
    return [];
  }

  switch (field?.type) {
    case 'boolean':
      return false;
    case 'duration':
      return { amount: 1, unit: 'hours' };
    default:
      return null;
  }
}

export const newRule = (): Rule => ({
  type: 'rule',
  field: '',
  operator: 'is' as Rule['operator'],
  value: null,
});

export const newGroup = (): Node => ({
  type: 'group',
  match: 'all',
  rules: [],
});

const keys = new WeakMap<object, string>();

/** A stable key for a rule or group, which have no id of their own. */
export function nodeKey(node: object): string {
  const raw = toRaw(node);

  if (!keys.has(raw)) {
    keys.set(raw, Math.random().toString(36).slice(2));
  }

  return keys.get(raw)!;
}

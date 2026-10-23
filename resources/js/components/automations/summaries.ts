import type { Step } from './steps';
import { parseConfigRules } from './configRules';

type Catalog = App.Data.Automations.AutomationCatalogData;
type Field = App.Data.Automations.CatalogFieldData;
type Rule = App.Data.Automations.RuleData;
type Group =
  | App.Data.Automations.ConditionGroupData
  | App.Data.Automations.ConditionStepData;

const fieldCache = new WeakMap<Catalog, Map<string, Field>>();

export function fieldFor(catalog: Catalog, key: string): Field | undefined {
  let fields = fieldCache.get(catalog);

  if (!fields) {
    fields = new Map(
      catalog.field_groups.flatMap((group) =>
        group.fields.map((field) => [field.key, field] as const),
      ),
    );
    fieldCache.set(catalog, fields);
  }

  return fields.get(key);
}

const plural = (amount: number, unit: string): string =>
  `${amount} ${amount === 1 ? unit.replace(/s$/, '') : unit}`;

/** "3 days ago", "in 2 hours" for a signed amount, negative being in the past. */
function relativeText(amount?: number, unit?: string): string {
  if (amount === undefined || !unit) {
    return '';
  }

  if (amount === 0) {
    return 'now';
  }

  return amount < 0
    ? `${plural(-amount, unit)} ago`
    : `in ${plural(amount, unit)}`;
}

function valueText(field: Field | undefined, value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  if (Array.isArray(value)) {
    return value.map((item) => valueText(field, item)).join(', ');
  }

  if (typeof value === 'object') {
    const { amount, unit, relative } = value as {
      amount?: number;
      unit?: string;
      relative?: { amount?: number; unit?: string };
    };

    if (relative) {
      return relativeText(relative.amount, relative.unit);
    }

    return amount !== undefined && unit ? plural(amount, unit) : '';
  }

  const text = String(value as string | number | boolean);

  return field?.options.find((option) => option.value === text)?.label ?? text;
}

function ruleText(rule: Rule, catalog: Catalog): string {
  const field = fieldFor(catalog, rule.field);
  const operator = field
    ? catalog.operators[field.type]?.find(
        (item) => item.value === rule.operator,
      )
    : undefined;

  return [
    field?.label ?? rule.field,
    operator?.label ?? rule.operator,
    valueText(field, rule.value),
  ]
    .filter(Boolean)
    .join(' ');
}

/** "Priority is Urgent and Tag includes vip", nested groups in parentheses. */
export function conditionText(
  group: Group,
  catalog: Catalog,
  nested = false,
): string {
  const parts = group.rules.map((node) =>
    node.type === 'group'
      ? conditionText(node, catalog, true)
      : ruleText(node, catalog),
  );
  const text = parts.join(group.match === 'any' ? ' or ' : ' and ');

  return nested && parts.length > 1 ? `(${text})` : text;
}

/** "1 hour", "2 days", "30 minutes" for a whole number of minutes. */
function minutesText(minutes: number): string {
  for (const [size, unit] of [
    [1440, 'days'],
    [60, 'hours'],
  ] as const) {
    if (minutes % size === 0) {
      return plural(minutes / size, unit);
    }
  }

  return plural(minutes, 'minutes');
}

export function summarizeStep(step: Step, catalog: Catalog): string {
  switch (step.type) {
    case 'condition':
      return step.rules.length
        ? `Only continue if ${conditionText(step, catalog)}`
        : 'Only continue if (no rules yet)';
    case 'action':
      if (step.action === 'set_field' && step.config.field) {
        const field = fieldFor(catalog, String(step.config.field));

        if (field) {
          return `Set ${field.label} to ${valueText(field, step.config.value) || 'nothing'}`;
        }
      }

      return (
        catalog.actions.find((action) => action.key === step.action)?.label ??
        (step.action || 'Choose an action')
      );
    case 'delay': {
      if (step.for) {
        return `Wait ${plural(step.for.amount, step.for.unit)}`;
      }

      if (step.until) {
        const label =
          fieldFor(catalog, step.until.field)?.label ?? step.until.field;
        const offset = step.until.offset;

        return offset
          ? `Wait until ${label} ${offset < 0 ? '−' : '+'} ${minutesText(Math.abs(offset))}`
          : `Wait until ${label}`;
      }

      return 'Wait';
    }
    case 'paths':
      return `Split into ${plural(step.branches.length + (step.else.length ? 1 : 0), 'paths')}`;
    case 'stop':
      return 'Stop';
    default:
      return 'Unknown step';
  }
}

export function summarizeTrigger(
  trigger: App.Data.Automations.TriggerData,
  catalog: Catalog,
): string {
  return (
    catalog.triggers.find((item) => item.key === trigger.type)?.label ??
    (trigger.type || 'Choose a trigger')
  );
}

/**
 * "Author: Agent · Visibility: Internal" for a trigger or action config, in rule order, with
 * the catalog's labels for fixed choices and `labels` for others (e.g. desk names by id).
 */
export function configSummary(
  config: Record<string, unknown>,
  rules: Record<string, string[]>,
  options: Record<string, App.Data.Automations.CatalogOptionData[]> = {},
  labels: Record<string, Record<string, string>> = {},
): string {
  const label = (key: string, value: unknown): string => {
    const text = String(value as string | number | boolean);

    return (
      options[key]?.find((option) => option.value === text)?.label ??
      labels[key]?.[text] ??
      text
    );
  };

  return parseConfigRules(rules)
    .flatMap(({ key, label: name }) => {
      const value = config[key];

      if (value === null || value === undefined || value === '') {
        return [];
      }

      if (typeof value === 'object' && !Array.isArray(value)) {
        return [];
      }

      const text = [value]
        .flat()
        .map((item) => label(key, item))
        .join(', ');

      return text
        ? [`${name}: ${text.length > 40 ? `${text.slice(0, 40)}…` : text}`]
        : [];
    })
    .join(' · ');
}

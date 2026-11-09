import { trans, transChoice } from 'laravel-vue-i18n';
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

/** "3 days", "1 hour", "2 business hours". */
function plural(amount: number, unit: string, business = false): string {
  const count = { count: String(amount) };

  switch (unit) {
    case 'minutes':
      return business
        ? transChoice(
            '{1} :count business minute|[0,*] :count business minutes',
            amount,
            count,
          )
        : transChoice('{1} :count minute|[0,*] :count minutes', amount, count);
    case 'hours':
      return business
        ? transChoice(
            '{1} :count business hour|[0,*] :count business hours',
            amount,
            count,
          )
        : transChoice('{1} :count hour|[0,*] :count hours', amount, count);
    case 'days':
      return business
        ? transChoice(
            '{1} :count business day|[0,*] :count business days',
            amount,
            count,
          )
        : transChoice('{1} :count day|[0,*] :count days', amount, count);
    default:
      return `${amount} ${unit}`;
  }
}

/** "3 days ago", "in 2 hours" for a signed amount, negative being in the past. */
function relativeText(amount?: number, unit?: string): string {
  if (amount === undefined || !unit) {
    return '';
  }

  if (amount === 0) {
    return trans('now');
  }

  return amount < 0
    ? trans(':duration ago', { duration: plural(-amount, unit) })
    : trans('in :duration', { duration: plural(amount, unit) });
}

export function valueText(field: Field | undefined, value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  if (typeof value === 'boolean') {
    return value ? trans('Yes') : trans('No');
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

/** An incomplete rule (no field, operator or needed value yet) has no text, and so is left out. */
function ruleText(rule: Rule, catalog: Catalog): string {
  if (!rule.field || !rule.operator) {
    return '';
  }

  const field = fieldFor(catalog, rule.field);
  const operator = field
    ? catalog.operators[field.type]?.find(
        (item) => item.value === rule.operator,
      )
    : undefined;

  const value = valueText(field, rule.value);

  if (operator?.takes_value && !value) {
    return '';
  }

  return [field?.label ?? rule.field, operator?.label ?? rule.operator, value]
    .filter(Boolean)
    .join(' ');
}

/** "Priority is Urgent and Tag includes vip", nested groups in parentheses. Empty parts are left out. */
export function conditionText(
  group: Group,
  catalog: Catalog,
  nested = false,
): string {
  const parts = group.rules
    .map((node) =>
      node.type === 'group'
        ? conditionText(node, catalog, true)
        : ruleText(node, catalog),
    )
    .filter(Boolean);
  const text = parts.join(
    group.match === 'any' ? ` ${trans('or')} ` : ` ${trans('and')} `,
  );

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

const truncate = (text: string, length = 40): string =>
  text.length > length ? `${text.slice(0, length)}…` : text;

/**
 * A one-line summary of an action from its config, or null to use the action's label.
 * `names` gives the name of a desk, macro, SLA policy or user by id.
 */
function actionText(
  action: string,
  config: Record<string, unknown>,
  names: Record<string, string>,
): string | null {
  const list = (ids: unknown): string =>
    Array.isArray(ids)
      ? ids.map((id) => names[String(id)] ?? String(id)).join(', ')
      : '';
  const text = (value: unknown, fallback = ''): string =>
    typeof value === 'string' ? value : fallback;
  const name = (id: unknown): string =>
    typeof id === 'string' ? (names[id] ?? '') : '';

  switch (action) {
    case 'tags': {
      const tags = Array.isArray(config.names) ? config.names.join(', ') : '';
      if (!tags) {
        return null;
      }

      switch (config.mode) {
        case 'remove':
          return trans('Remove tags :tags', { tags: truncate(tags) });
        case 'replace':
          return trans('Replace tags :tags', { tags: truncate(tags) });
        default:
          return trans('Add tags :tags', { tags: truncate(tags) });
      }
    }
    case 'assign': {
      const people = list(config.user_ids);

      switch (config.mode) {
        case 'unassign_all':
          return trans('Unassign everyone');
        case 'add':
          return people
            ? trans('Assign to :people', { people: truncate(people) })
            : null;
        case 'replace':
          return people
            ? trans('Set assignees to :people', { people: truncate(people) })
            : null;
        case 'round_robin':
          return trans('Assign by round robin');
        case 'load_balanced':
          return trans('Assign by load balancing');
        default:
          return null;
      }
    }
    case 'move_desk':
      return name(config.desk_id)
        ? trans('Move to :desk', { desk: name(config.desk_id) })
        : null;
    case 'apply_macro':
      return name(config.macro_id)
        ? trans('Apply the macro :macro', { macro: name(config.macro_id) })
        : null;
    case 'set_sla_policy':
      return name(config.sla_policy_id)
        ? trans('Set the SLA policy to :policy', {
            policy: name(config.sla_policy_id),
          })
        : null;
    case 'send_webhook':
      return config.url
        ? truncate(`${text(config.method, 'POST')} ${text(config.url)}`, 60)
        : null;
    case 'notify':
      return config.subject
        ? trans('Notify: :subject', { subject: truncate(text(config.subject)) })
        : null;
    case 'add_note':
    case 'send_reply':
      if (!config.body) {
        return null;
      }

      return trans(
        action === 'add_note' ? 'Add a note: :body' : 'Send a reply: :body',
        { body: truncate(text(config.body).replace(/\s+/g, ' ')) },
      );
    case 'set_reminder': {
      const after = config.after as
        | { amount?: number; unit?: string }
        | undefined;

      return after?.amount && after.unit
        ? trans('Remind in :duration', {
            duration: plural(after.amount, after.unit),
          })
        : null;
    }
    default:
      return null;
  }
}

export function summarizeStep(
  step: Step,
  catalog: Catalog,
  names: Record<string, string> = {},
): string {
  switch (step.type) {
    case 'condition':
      return trans('Only continue if :condition', {
        condition: conditionText(step, catalog) || trans('(no rules yet)'),
      });
    case 'action':
      if (step.action === 'set_field' && step.config.field) {
        const field = fieldFor(catalog, String(step.config.field));

        if (field) {
          return trans('Set :field to :value', {
            field: field.label,
            value: valueText(field, step.config.value) || trans('nothing'),
          });
        }
      }

      return (
        actionText(step.action, step.config, names) ??
        catalog.actions.find((action) => action.key === step.action)?.label ??
        (step.action || trans('Choose an action'))
      );
    case 'delay': {
      if (step.for) {
        const { amount, unit, business_schedule_id: schedule } = step.for;

        return trans('Wait :duration', {
          duration: plural(amount, unit, !!schedule),
        });
      }

      if (step.until) {
        const label =
          fieldFor(catalog, step.until.field)?.label ?? step.until.field;
        const offset = step.until.offset;

        if (!offset) {
          return trans('Wait until :field', { field: label });
        }

        return trans(
          offset < 0
            ? 'Wait until :duration before :field'
            : 'Wait until :duration after :field',
          { duration: minutesText(Math.abs(offset)), field: label },
        );
      }

      return trans('Wait');
    }
    case 'paths': {
      const names = step.branches.map(
        (branch) => branch.name || trans('Unnamed'),
      );

      return transChoice(
        '{1} :count path: :names|[0,*] :count paths: :names',
        names.length,
        {
          count: String(names.length),
          names: [
            ...names,
            ...(step.else.length ? [trans('otherwise')] : []),
          ].join(', '),
        },
      );
    }
    case 'stop':
      return trans('Stop');
    default:
      return trans('Unknown step');
  }
}

/** "Cancelled if: A message is added" for a delay that lists triggers, or undefined. */
export function cancelSummary(
  step: Step,
  catalog: Catalog,
): string | undefined {
  if (step.type !== 'delay' || !step.cancel_on.length) {
    return undefined;
  }

  return trans('Cancelled if: :triggers', {
    triggers: step.cancel_on
      .map(
        (type) =>
          catalog.triggers.find((trigger) => trigger.key === type)?.label ??
          type,
      )
      .join(', '),
  });
}

export function summarizeTrigger(
  trigger: App.Data.Automations.TriggerData,
  catalog: Catalog,
): string {
  return (
    catalog.triggers.find((item) => item.key === trigger.type)?.label ??
    (trigger.type || trans('Choose a trigger'))
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

export type ConfigFieldKind =
  | 'select'
  | 'checkbox'
  | 'number'
  | 'list'
  | 'time'
  | 'textarea'
  | 'text';

export type ConfigField = {
  key: string;
  label: string;
  kind: ConfigFieldKind;
  required: boolean;
  /** `present` keys must be sent even when empty. */
  present: boolean;
  options: string[];
  min?: number;
  max?: number;
};

type Rules = Record<string, string[]>;
type Config = Record<string, unknown>;
type Options = Record<string, App.Data.Automations.CatalogOptionData[]>;

const arg = (rules: string[], name: string): string[] | undefined =>
  rules
    .find((rule) => rule.startsWith(`${name}:`))
    ?.slice(name.length + 1)
    .split(',');

const humanize = (key: string): string => {
  const words = key.replace(/_id$/, '').replaceAll('_', ' ');

  return words.charAt(0).toUpperCase() + words.slice(1);
};

/**
 * One field per top-level key of a catalog `config_rules`. Nested keys (`headers.*`)
 * can't be rendered by a generic form and are skipped, as are lists of objects.
 */
export function parseConfigRules(rules: Rules): ConfigField[] {
  return (
    Object.entries(rules)
      .filter(([key]) => !key.includes('.'))
      // A list of objects (`headers.*.name`) can't be edited as plain text.
      .filter(
        ([key]) =>
          !Object.keys(rules).some((other) => other.startsWith(`${key}.*.`)),
      )
      .map(([key, keyRules]) => {
        const options = arg(keyRules, 'in') ?? [];
        const max = Number(arg(keyRules, 'max')?.[0]);
        const min = Number(arg(keyRules, 'min')?.[0]);
        const numeric = ['integer', 'numeric', 'decimal'].some((rule) =>
          keyRules.includes(rule),
        );

        const kind: ConfigFieldKind = options.length
          ? 'select'
          : keyRules.includes('boolean')
            ? 'checkbox'
            : numeric
              ? 'number'
              : keyRules.includes('array')
                ? 'list'
                : keyRules.includes('date_format:H:i')
                  ? 'time'
                  : max >= 500
                    ? 'textarea'
                    : 'text';

        return {
          key,
          label: humanize(key),
          kind,
          required:
            keyRules.includes('required') ||
            keyRules.some((rule) => rule.startsWith('required_if:')),
          present: keyRules.includes('present'),
          options,
          min: Number.isNaN(min) ? undefined : min,
          max: numeric && !Number.isNaN(max) ? max : undefined,
        };
      })
  );
}

/**
 * `required_if:mode,add` and `prohibited_unless:mode,add` tie a key to another key's value,
 * so the key is only shown (and sent) while that value matches.
 */
export function isVisible(keyRules: string[], config: Config): boolean {
  return ['required_if', 'prohibited_unless'].every((name) => {
    const [dependsOn, ...values] = arg(keyRules, name) ?? [];

    return (
      dependsOn === undefined ||
      values.includes(
        String((config[dependsOn] ?? '') as string | number | boolean),
      )
    );
  });
}

/**
 * Required `in:` keys that are shown but empty start on their first choice. `options` are the
 * catalog's labelled choices, which keep the order of the `in:` rule.
 */
function fillRequiredChoices(
  config: Config,
  rules: Rules,
  options: Options,
): Config {
  const filled = { ...config };

  // A key can show up only once another got its value, so go over the fields until none changes.
  for (let pass = 0; pass < 3; pass++) {
    for (const field of parseConfigRules(rules)) {
      const first = options[field.key]?.[0]?.value ?? field.options[0];

      if (
        field.required &&
        first !== undefined &&
        field.kind === 'select' &&
        filled[field.key] === undefined &&
        isVisible(rules[field.key], filled)
      ) {
        filled[field.key] = first;
      }
    }
  }

  return filled;
}

export const defaultConfig = (rules: Rules, options: Options = {}): Config =>
  fillRequiredChoices({}, rules, options);

/** The config without the keys whose condition no longer holds, and with the required choices filled. */
export function normalizeConfig(
  config: Config,
  rules: Rules,
  options: Options = {},
): Config {
  return fillRequiredChoices(
    Object.fromEntries(
      Object.entries(config).filter(
        ([key]) => !rules[key] || isVisible(rules[key], config),
      ),
    ),
    rules,
    options,
  );
}

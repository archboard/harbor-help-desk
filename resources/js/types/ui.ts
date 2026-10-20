export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type FlashToast = {
  type: 'success' | 'info' | 'warning' | 'error';
  message: string;
};

export type AlertLevel = 'success' | 'neutral' | 'warning' | 'error';

export type ButtonSize = 'xs' | 'sm' | 'base' | 'lg' | 'xl';

export type ButtonColor =
  | 'primary'
  | 'red'
  | 'yellow'
  | 'green'
  | 'gray'
  | 'gray_light'
  | 'white';

export type MenuLink = {
  label: string;
  href: string;
};

export type SelectOption = {
  value: string | number | boolean;
  label: string;
};

/**
 * Anything the option normalizer accepts: plain values, `{ value, label }` /
 * `{ id, name }` objects, or a `{ value: label }` map.
 */
export type OptionsInput =
  | Array<
      | string
      | number
      | {
          value?: string | number | boolean;
          id?: string | number;
          label?: string;
          name?: string;
        }
    >
  | Record<string, string>;

/** Shared props for the `forms/fields/*Field` shorthands. */
export type FieldProps = {
  label?: string;
  error?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  helpText?: string;
};

/** One page of a paginator, as `Inertia::scroll()` sends it. */
export type Paginated<T> = {
  data: T[];
};

/** A ticket's value for one custom field, by the field's type. */
export type CustomFieldValue = string | number | boolean | string[] | null;

import type { OptionsInput, SelectOption } from '@/types';

export function normalizeOptions(options?: OptionsInput): SelectOption[] {
  if (!options) {
    return [];
  }

  if (!Array.isArray(options)) {
    return Object.entries(options).map(([value, label]) => ({ value, label }));
  }

  return options.map((option) => {
    if (typeof option !== 'object') {
      return { value: option, label: String(option) };
    }

    const label = option.label ?? option.name ?? '';

    return { value: option.value ?? option.id ?? label, label };
  });
}

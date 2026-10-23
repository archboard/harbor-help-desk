import type { Component } from 'vue';
import SetFieldConfig from './SetFieldConfig.vue';

/**
 * Editors for the actions that have one, by action key. The others use the generic `ConfigForm`.
 * Each takes `v-model` (the config), `catalog` and `errors` (keyed by config key).
 */
export const actionEditors: Record<string, Component> = {
  set_field: SetFieldConfig,
};

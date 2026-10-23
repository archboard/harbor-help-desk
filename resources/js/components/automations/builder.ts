import type { ComputedRef, InjectionKey, Ref } from 'vue';
import type { CardError } from './errors';
import type { LogEntry } from './testResult';

/**
 * What every step list of the builder needs, provided by the page so that the lists nested
 * in paths don't pass it down one level at a time.
 */
export type BuilderContext = {
  catalog: ComputedRef<App.Data.Automations.AutomationCatalogData>;
  /** The trigger's key. */
  trigger: ComputedRef<string>;
  /** The field groups the trigger makes available to rules. */
  groups: ComputedRef<App.Automations.FieldGroup[]>;
  /** Names by id, for the summaries of what an action points at. */
  names: ComputedRef<Record<string, string>>;
  desks: ComputedRef<App.Data.Desks.DeskData[]>;
  moveDesks: ComputedRef<App.Data.Desks.DeskData[]>;
  schedules: ComputedRef<App.Data.BusinessSchedules.BusinessScheduleData[]>;
  macros: ComputedRef<App.Data.Macros.MacroData[]>;
  slaPolicies: ComputedRef<App.Data.SlaPolicies.SlaPolicyData[]>;
  /** The desk the automation is scoped to, or null for the whole workspace. */
  scopeDeskId: ComputedRef<string | null>;
  /** The server's errors keyed `definition.…`. */
  errors: ComputedRef<Record<string, string>>;
  problems: ComputedRef<CardError[]>;
  /** The test run's log by step id, or null while there is no result to show. */
  testLog: ComputedRef<Map<string, LogEntry> | null>;
  /** The card that is expanded: the trigger, a step id, or none. */
  openCard: Ref<string | null>;
};

export const builderKey: InjectionKey<BuilderContext> = Symbol('builder');

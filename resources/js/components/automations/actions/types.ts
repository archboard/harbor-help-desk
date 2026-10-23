/** What every action editor receives. An editor declares them all and uses the ones it needs. */
export type ActionEditorProps = {
  catalog: App.Data.Automations.AutomationCatalogData;
  /** The trigger's key, for the placeholders it offers. */
  trigger: string;
  /** Server errors keyed by config key. */
  errors?: Record<string, string>;
  desks: App.Data.Desks.DeskData[];
  /** The desks a move action may target. */
  moveDesks: App.Data.Desks.DeskData[];
  schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** The shared macros the editor may apply. */
  macros: App.Data.Macros.MacroData[];
  slaPolicies: App.Data.SlaPolicies.SlaPolicyData[];
  /** The desk the automation is scoped to, or null for the whole workspace. */
  scopeDeskId: string | null;
};

/** The labelled choices of a config key, from the catalog's `config_options` for the action. */
export function optionsOf(
  catalog: App.Data.Automations.AutomationCatalogData,
  action: string,
  key: string,
): App.Data.Automations.CatalogOptionData[] {
  return (
    catalog.actions.find((item) => item.key === action)?.config_options[key] ??
    []
  );
}

export type RunStatusColor = 'blue' | 'yellow' | 'green' | 'gray' | 'red';

/** The pill color of each run status, shared by the list of automations and the run history. */
export const statusColors: Record<
  App.Enums.AutomationRunStatus,
  RunStatusColor
> = {
  running: 'blue',
  waiting: 'yellow',
  completed: 'green',
  skipped: 'gray',
  failed: 'red',
  cancelled: 'gray',
};

/** How long a finished run took, or a dash while it is still going. */
export function runDuration(run: {
  status: App.Enums.AutomationRunStatus;
  created_at: string | null;
  updated_at: string | null;
}): string {
  if (
    !run.created_at ||
    !run.updated_at ||
    run.status === 'running' ||
    run.status === 'waiting'
  ) {
    return '—';
  }

  const seconds = Math.round(
    (Date.parse(run.updated_at) - Date.parse(run.created_at)) / 1000,
  );

  return seconds < 1
    ? '< 1s'
    : seconds < 60
      ? `${seconds}s`
      : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

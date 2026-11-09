import { trans } from 'laravel-vue-i18n';

/**
 * What a test run reports, typed from the entries {@see App\Automations\Engine} and
 * {@see App\Automations\ConditionEvaluator} write: a run's log has the same shape.
 */
export type RuleResult = {
  type: 'rule';
  field: string;
  operator: string;
  expected: unknown;
  actual: unknown;
  passed: boolean;
  error?: string;
};

export type GroupResult = {
  type: 'group';
  match: 'all' | 'any';
  passed: boolean;
  rules: (RuleResult | GroupResult)[];
};

export type LogEntry = {
  step_id: string;
  type: string;
  error?: string;
  /** A condition step. */
  passed?: boolean;
  /** A condition step, or the chosen branch of a paths step. */
  condition?: GroupResult;
  /** An action step: what it would do. */
  action?: string;
  result?: string;
  /** A paths step: the chosen branch's name, `'else'`, or null when nothing ran. */
  branch?: string | null;
  branch_index?: number;
  /** A delay step. */
  resume_at?: string;
  waiting?: boolean;
  note?: string;
  /** A run's webhook outcome, and why a run was skipped or cancelled. */
  status?: number | null;
  duration_ms?: number;
  attempt?: number;
  reason?: string;
};

export type TestResult = Omit<
  App.Data.Automations.AutomationTestResultData,
  'log'
> & { log: LogEntry[] };

export type StepStatus = 'pass' | 'fail' | 'skip';

/** A step that left no log entry was not reached. */
export function statusOf(entry: LogEntry | undefined): StepStatus {
  if (!entry) {
    return 'skip';
  }

  return entry.error || entry.passed === false ? 'fail' : 'pass';
}

/** The line a step card shows under its header. */
export function noteOf(entry: LogEntry | undefined): string | undefined {
  if (!entry) {
    return trans('Not reached.');
  }

  if (entry.error) {
    return entry.error;
  }

  switch (entry.type) {
    case 'action':
      return entry.result;
    case 'delay':
      return entry.note;
    case 'stop':
      return trans('Stopped: later steps and automations are skipped.');
    case 'condition':
      return entry.passed
        ? trans('Passed.')
        : trans('Did not pass: the steps after it did not run.');
    case 'paths':
      return entry.branch === 'else'
        ? trans('Took “Otherwise”.')
        : entry.branch
          ? trans('Took “:branch”.', { branch: entry.branch })
          : trans('No path matched.');
    default:
      return undefined;
  }
}

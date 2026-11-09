import {
  CircleAlert,
  Filter,
  GitBranch,
  Hourglass,
  OctagonX,
  Zap,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { Component } from 'vue';

export type Definition = App.Data.Automations.AutomationDefinitionData;
export type Step = Definition['steps'][number];
export type StepType = Step['type'];

export const stepTypes: Record<
  StepType,
  { label: string; icon: Component; addable: boolean }
> = {
  condition: {
    get label() {
      return trans('Condition');
    },
    icon: Filter,
    addable: true,
  },
  action: {
    get label() {
      return trans('Action');
    },
    icon: Zap,
    addable: true,
  },
  delay: {
    get label() {
      return trans('Delay');
    },
    icon: Hourglass,
    addable: true,
  },
  paths: {
    get label() {
      return trans('Paths');
    },
    icon: GitBranch,
    addable: true,
  },
  stop: {
    get label() {
      return trans('Stop');
    },
    icon: OctagonX,
    addable: true,
  },
};

/** What the "+" menu offers. A step type joins it by setting `addable` and handling it in `newStep`. */
export const addableStepTypes = (
  Object.entries(stepTypes) as [StepType, (typeof stepTypes)[StepType]][]
).filter(([, meta]) => meta.addable);

export const unknownStepIcon = CircleAlert;

/** The server accepts letters, numbers, dashes and underscores up to 64 characters. */
export function newStepId(): string {
  return (
    globalThis.crypto?.randomUUID?.() ??
    `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`
  );
}

export type Branch = App.Data.Automations.BranchData;

/** A branch named after its position ("Path A"), that matches every ticket until its condition has rules. */
export const newBranch = (index: number): Branch => ({
  name: trans('Path :letter', {
    letter: String.fromCharCode(65 + (index % 26)),
  }),
  condition: { type: 'group', match: 'all', rules: [] },
  steps: [],
});

export function newStep(type: StepType): Step {
  const id = newStepId();

  switch (type) {
    case 'action':
      return { id, type, action: '', config: {} };
    case 'condition':
      return { id, type, match: 'all', rules: [] };
    case 'delay':
      return {
        id,
        type,
        for: { amount: 1, unit: 'hours', business_schedule_id: null },
        until: null,
        cancel_on: [],
      };
    case 'paths':
      return { id, type, branches: [newBranch(0)], else: [] };
    case 'stop':
      return { id, type };
  }
}

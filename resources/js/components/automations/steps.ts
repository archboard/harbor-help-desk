import {
  CircleAlert,
  Filter,
  GitBranch,
  Hourglass,
  OctagonX,
  Zap,
} from '@lucide/vue';
import type { Component } from 'vue';

export type Definition = App.Data.Automations.AutomationDefinitionData;
export type Step = Definition['steps'][number];
export type StepType = Step['type'];

export const stepTypes: Record<
  StepType,
  { label: string; icon: Component; addable: boolean }
> = {
  condition: { label: 'Condition', icon: Filter, addable: false },
  action: { label: 'Action', icon: Zap, addable: true },
  delay: { label: 'Delay', icon: Hourglass, addable: false },
  paths: { label: 'Paths', icon: GitBranch, addable: false },
  stop: { label: 'Stop', icon: OctagonX, addable: true },
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

export function newStep(type: StepType): Step {
  const id = newStepId();

  switch (type) {
    case 'action':
      return { id, type, action: '', config: {} };
    case 'stop':
      return { id, type };
    default:
      throw new Error(`The ${type} step can't be added yet.`);
  }
}

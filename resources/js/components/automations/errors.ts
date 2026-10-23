import type { Step } from './steps';

export const TRIGGER_CARD = 'trigger';

export type CardError = {
  /** The card the error belongs to: a step (nested ones too), the trigger, or null for errors about the automation as a whole. */
  cardId: string | null;
  /** The top-level card that holds `cardId`, which is the one the error summary names. */
  rootId: string | null;
  message: string;
  /** Shown beside the field it is about, so the card's own list leaves it out. */
  inline: boolean;
};

function findStep(steps: Step[], id: string): Step | undefined {
  for (const step of steps) {
    if (step.id === id) {
      return step;
    }

    const found =
      step.type === 'paths'
        ? findStep(
            [...step.branches.flatMap((branch) => branch.steps), ...step.else],
            id,
          )
        : undefined;

    if (found) {
      return found;
    }
  }
}

/** Whether `id` is one of `steps` or nested anywhere inside them. */
export const containsStep = (steps: Step[], id: string): boolean =>
  findStep(steps, id) !== undefined;

/**
 * Maps the server's `definition.*` errors to cards. The server keys them by step id
 * (`definition.steps.<id>.config.value`), also for steps nested in paths, so a nested
 * step's error lands on its own card.
 */
export function cardErrors(
  errors: Record<string, string>,
  steps: Step[],
): CardError[] {
  return Object.entries(errors)
    .filter(([key]) => key.startsWith('definition'))
    .map(([key, message]) => {
      const [, scope, id, part, , sub] = key.split('.');
      const step = scope === 'steps' && id ? findStep(steps, id) : undefined;
      const root = step
        ? steps.find((item) => containsStep([item], step.id))
        : undefined;

      // These show their errors beside the inputs they are about.
      const inline =
        (step?.type === 'condition' &&
          (part === 'rules' || part === 'match')) ||
        (step?.type === 'action' && part === 'action') ||
        (step?.type === 'delay' &&
          (part === 'for' || part === 'until' || part === 'cancel_on')) ||
        (step?.type === 'paths' &&
          part === 'branches' &&
          (sub === 'name' || sub === 'condition'));

      return {
        cardId: scope === 'trigger' ? TRIGGER_CARD : (step?.id ?? null),
        rootId: scope === 'trigger' ? TRIGGER_CARD : (root?.id ?? null),
        message,
        inline,
      };
    });
}

/** The errors under `prefix`, keyed by what follows it (`definition.trigger.config.` gives `{ time: '…' }`). */
export function errorsUnder(
  errors: Record<string, string>,
  prefix: string,
): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors)
      .filter(([key]) => key.startsWith(prefix))
      .map(([key, message]) => [key.slice(prefix.length), message]),
  );
}

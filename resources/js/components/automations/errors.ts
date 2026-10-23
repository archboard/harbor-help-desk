import type { Step } from './steps';

export const TRIGGER_CARD = 'trigger';

export type CardError = {
  /** The top-level card the error belongs to, or null for errors about the automation as a whole. */
  cardId: string | null;
  message: string;
  /** Shown beside the field it is about, so the card's own list leaves it out. */
  inline: boolean;
};

function containsStep(steps: Step[], id: string): boolean {
  return steps.some(
    (step) =>
      step.id === id ||
      (step.type === 'paths' &&
        (step.branches.some((branch) => containsStep(branch.steps, id)) ||
          containsStep(step.else, id))),
  );
}

/**
 * Maps the server's `definition.*` errors to cards. The server keys them by step id
 * (`definition.steps.<id>.config.value`), also for steps nested in paths, so a nested
 * step's error lands on the top-level card that holds it.
 */
export function cardErrors(
  errors: Record<string, string>,
  steps: Step[],
): CardError[] {
  return Object.entries(errors)
    .filter(([key]) => key.startsWith('definition'))
    .map(([key, message]) => {
      const [, scope, id, part] = key.split('.');
      const cardId =
        scope === 'trigger'
          ? TRIGGER_CARD
          : scope === 'steps' && id
            ? (steps.find((step) => containsStep([step], id))?.id ?? null)
            : null;

      // A condition step's rules and match mode show their errors on their own rows.
      const inline =
        scope === 'steps' &&
        (part === 'rules' || part === 'match') &&
        steps.some((step) => step.id === id && step.type === 'condition');

      return { cardId, message, inline };
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

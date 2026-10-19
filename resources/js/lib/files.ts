/**
 * A file size for people, e.g. "12 KB" or "3.4 MB".
 */
export function fileSize(bytes: number): string {
  return bytes < 1024 * 1024
    ? `${Math.max(1, Math.round(bytes / 1024))} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/**
 * The first error for an uploaded list, which comes back keyed `attachments.0`, `attachments.1`…
 */
export function firstListError(
  errors: Partial<Record<string, string>>,
  key: string,
): string | undefined {
  return (
    errors[key] ??
    Object.entries(errors).find(([name]) => name.startsWith(`${key}.`))?.[1]
  );
}

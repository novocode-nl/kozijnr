/**
 * Maps an action's `fieldErrors` (see `lib/forms/types.ts`'s `ActionResult`)
 * onto react-hook-form's `setError`, so a server-side failure like
 * "e-mailadres al in gebruik" shows up next to the right field through the
 * same field-wrapper as a validation error, instead of only as a toast.
 *
 * Kept as a small setError-agnostic function (rather than reaching into a
 * `useForm()` instance directly) so it's testable as plain logic, matching
 * this project's existing Vitest setup (node environment, no React
 * rendering — see `vitest.config.mts`).
 */
export type SetFieldError = (
  name: string,
  error: { type: string; message: string }
) => void

export function applyFieldErrors(
  setError: SetFieldError,
  fieldErrors: Record<string, string> | undefined,
  knownFieldNames: readonly string[]
): { applied: string[]; unmatched: Record<string, string> } {
  const applied: string[] = []
  const unmatched: Record<string, string> = {}

  if (!fieldErrors) {
    return { applied, unmatched }
  }

  for (const [name, message] of Object.entries(fieldErrors)) {
    if (knownFieldNames.includes(name)) {
      setError(name, { type: "server", message })
      applied.push(name)
    } else {
      unmatched[name] = message
    }
  }

  return { applied, unmatched }
}

/**
 * Maps an action's `fieldErrors` onto react-hook-form's `setError`, so a
 * server-side failure shows up next to the right field instead of only as
 * a toast. Kept setError-agnostic so it's testable as plain logic.
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

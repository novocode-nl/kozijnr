/**
 * Applies the optional `transformSubmit` prop to validated form values,
 * producing whatever shape the action/API expects. Deliberately separate
 * from Zod validation: the schema decides whether the values are *valid*,
 * this decides what *shape* gets sent.
 */
export function resolveSubmitPayload<TValues, TSubmit = TValues>(
  values: TValues,
  transformSubmit?: (values: TValues) => TSubmit
): TSubmit {
  if (!transformSubmit) {
    return values as unknown as TSubmit
  }

  return transformSubmit(values)
}

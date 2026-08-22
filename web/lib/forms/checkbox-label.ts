/**
 * Resolves the two label slots a checkbox field renders (the top-level
 * label above the control, and the option text beside it) from a single
 * `label`/`optionLabel` pair. When `optionLabel` is omitted, the top-level
 * label is dropped so the label text only appears once.
 */
export function resolveCheckboxLabel(field: {
  label: string
  optionLabel?: string
}): { topLabel: string | null; optionText: string } {
  if (field.optionLabel) {
    return { topLabel: field.label, optionText: field.optionLabel }
  }
  return { topLabel: null, optionText: field.label }
}

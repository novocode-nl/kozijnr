/**
 * KOZ-19 rework fix: resolves the two label slots a checkbox field renders
 * (the top-level `FieldLabel` above the control, and the option text beside
 * the control itself) from a single `label`/`optionLabel` pair.
 *
 * When `optionLabel` is set, both slots are shown (top-level label + distinct
 * option text next to the checkbox — see the "Nieuwsbrief" field in the
 * ConfigForm demo). When it's omitted, the top-level label is dropped so the
 * label text only appears once, next to the control — matching the
 * single-label behaviour every checkbox config had before this rework, and
 * the "unchanged" promise `CheckboxFieldConfig.optionLabel` makes in
 * `types.ts`.
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

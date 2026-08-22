import type { FieldConfig } from "@/lib/forms/types"

/**
 * Whether a single field should be shown, given the current (whole-form)
 * values. Fields without a `visibleWhen` are always visible.
 */
export function isFieldVisible<TValues>(
  field: Pick<FieldConfig<TValues>, "visibleWhen">,
  values: TValues
): boolean {
  return field.visibleWhen ? field.visibleWhen(values) : true
}

/** Filters a list of field configs down to the ones currently visible. */
export function getVisibleFields<TValues, TField extends Pick<FieldConfig<TValues>, "visibleWhen">>(
  fields: TField[],
  values: TValues
): TField[] {
  return fields.filter((field) => isFieldVisible(field, values))
}

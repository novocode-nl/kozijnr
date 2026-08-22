import type { FieldValues } from "react-hook-form"
import type { z } from "zod"

/**
 * Shared vocabulary between a form's config object (field definitions +
 * Zod schema) and the `<ConfigForm>` component that renders/validates/
 * submits it. Nothing here is login- or CRUD-specific.
 */

/** An option for `select` and `combobox` fields. */
export type SelectOption = {
  value: string
  label: string
}

/**
 * Result shape an `Action` resolves to. Mirrors `lib/api.ts`'s
 * `LoginResult` shape, extended with an optional `fieldErrors` map so an
 * action can attach a failure to a specific field.
 */
export type ActionResult<TResult = void> =
  | { success: true; data?: TResult }
  | {
      success: false
      message?: string
      /** Field name (matching a `FieldConfig["name"]`) -> error message. */
      fieldErrors?: Record<string, string>
    }

/** A submit action, consistent with how `lib/api.ts` exposes actions today. */
export type FormAction<TSubmit, TResult = void> = (
  values: TSubmit
) => Promise<ActionResult<TResult>>

interface BaseFieldConfig<TValues> {
  /** Key into the form's values object. Flat keys only — no repeatable/array fields. */
  name: keyof TValues & string
  label: string
  placeholder?: string
  /** Optional helper text shown when the field has no error. */
  hint?: string
  /**
   * Where `hint` renders relative to the control. Defaults to
   * `"belowControl"`; `"belowLabel"` renders it under the top-level label.
   */
  hintPlacement?: "belowLabel" | "belowControl"
  disabled?: boolean
  /**
   * UI-only visibility condition based on the current (whole-form) values.
   * Distinct from `transformSubmit`: this decides what's shown, not what's
   * sent to the action.
   */
  visibleWhen?: (values: TValues) => boolean
}

export type TextFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "text" | "email" | "password"
  autoComplete?: string
}

export type NumberFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "number"
}

export type TextareaFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "textarea"
  rows?: number
}

export type CheckboxFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "checkbox"
  /**
   * Text shown beside the checkbox control itself, e.g. "Ja, ik wil de
   * nieuwsbrief ontvangen" under a top-level `label` of "Nieuwsbrief".
   * Falls back to `label` when omitted, so existing single-string configs
   * keep working unchanged.
   */
  optionLabel?: string
}

export type SelectFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "select"
  options: SelectOption[]
}

/** Single-select radio-button group, sharing `SelectOption` with `select`. */
export type RadioFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "radio"
  options: SelectOption[]
}

export type ComboboxFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "combobox"
  options: SelectOption[]
  /** Multiple-select variant. */
  multiple?: boolean
}

export type FieldConfig<TValues> =
  | TextFieldConfig<TValues>
  | NumberFieldConfig<TValues>
  | TextareaFieldConfig<TValues>
  | CheckboxFieldConfig<TValues>
  | SelectFieldConfig<TValues>
  | RadioFieldConfig<TValues>
  | ComboboxFieldConfig<TValues>

export interface ConfigFormProps<
  TValues extends FieldValues,
  TSubmit = TValues,
  TResult = void,
> {
  /** Field definitions. Order determines render order. */
  fields: FieldConfig<TValues>[]
  /**
   * Single Zod schema validating the whole form — not per-field configs.
   * Use `.superRefine()` on the schema for conditional requirements.
   */
  schema: z.ZodType<TValues>
  /** Pre-filled values, e.g. for a CRUD edit screen. */
  defaultValues: TValues
  /** Submit action, called with the (optionally transformed) validated values. */
  action: FormAction<TSubmit, TResult>
  /**
   * Optional shape transform from validated form values to whatever the
   * action/API expects. Kept separate from `visibleWhen` on purpose: one is
   * a UI concern (what's shown), the other is a data concern (what's sent).
   */
  transformSubmit?: (values: TValues) => TSubmit
  /** Called after a successful submit. */
  onSuccess?: (result: TResult | undefined, values: TValues) => void
  submitLabel?: string
  pendingLabel?: string
  className?: string
}

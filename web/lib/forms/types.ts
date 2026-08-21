import type { FieldValues } from "react-hook-form"
import type { z } from "zod"

/**
 * KOZ-17: generic config-driven form component.
 *
 * These types are the shared vocabulary between a form's config object
 * (field definitions + Zod schema) and the `<ConfigForm>` component that
 * renders/validates/submits it. Nothing here is login- or CRUD-specific —
 * that's the whole point of the ticket.
 */

/** An option for `select` and `combobox` fields. */
export type SelectOption = {
  value: string
  label: string
}

/**
 * Result shape an `Action` (the submit handler passed to `<ConfigForm>`)
 * resolves to. Mirrors the `{ success: true } | { success: false, message }`
 * shape already used by `lib/api.ts`'s `login`/`adminLogin` (see
 * `LoginResult`), extended with an optional `fieldErrors` map so an action
 * can attach a failure to a specific field (e.g. "e-mailadres al in
 * gebruik") instead of only surfacing a generic toast/banner message.
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
  /** Key into the form's values object. Flat keys only — no repeatable/array fields (out of scope for KOZ-17). */
  name: keyof TValues & string
  label: string
  placeholder?: string
  /** Optional helper text shown under the control when the field has no error. */
  hint?: string
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
}

export type SelectFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "select"
  options: SelectOption[]
}

export type ComboboxFieldConfig<TValues> = BaseFieldConfig<TValues> & {
  type: "combobox"
  options: SelectOption[]
  /** Multiple-select variant, required by KOZ-17's DoD. */
  multiple?: boolean
}

export type FieldConfig<TValues> =
  | TextFieldConfig<TValues>
  | NumberFieldConfig<TValues>
  | TextareaFieldConfig<TValues>
  | CheckboxFieldConfig<TValues>
  | SelectFieldConfig<TValues>
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

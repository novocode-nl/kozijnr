"use client"

import { useState } from "react"
import {
  useForm,
  useWatch,
  type DefaultValues,
  type FieldValues,
  type Resolver,
} from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { FieldGroup } from "@/components/ui/field"
import { ConfigFormField } from "@/components/config-form/config-form-field"
import { applyFieldErrors, type SetFieldError } from "@/lib/forms/field-errors"
import { getVisibleFields } from "@/lib/forms/visibility"
import { resolveSubmitPayload } from "@/lib/forms/submit-payload"
import type { ConfigFormProps } from "@/lib/forms/types"

/**
 * Generic, config-driven form component. Renders a Zod-validated form from
 * a list of `FieldConfig`s and hands the validated (optionally
 * `transformSubmit`-shaped) values to a submit `Action`. Deliberately
 * contains no login- or CRUD-specific logic.
 */
export function ConfigForm<
  TValues extends FieldValues,
  TSubmit = TValues,
  TResult = void,
>({
  fields,
  schema,
  defaultValues,
  action,
  transformSubmit,
  onSuccess,
  submitLabel = "Opslaan",
  pendingLabel = "Bezig...",
  className,
}: ConfigFormProps<TValues, TSubmit, TResult>) {
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Casts below are the price of a *generic* form: the schema type is only
  // known through the `TValues` type parameter, so `zodResolver`/`useForm`
  // can't infer it directly. Runtime behavior is unaffected.
  const form = useForm<TValues, unknown, TValues>({
    resolver: zodResolver(schema as never) as unknown as Resolver<TValues, unknown, TValues>,
    defaultValues: defaultValues as DefaultValues<TValues>,
  })

  const { handleSubmit, control, setError } = form

  const values = useWatch({ control })
  const visibleFields = getVisibleFields(fields, values as TValues)
  const knownFieldNames = fields.map((field) => field.name)

  async function onSubmit(data: TValues) {
    setFormError(null)
    setIsSubmitting(true)

    const payload = resolveSubmitPayload<TValues, TSubmit>(data, transformSubmit)
    const result = await action(payload)

    setIsSubmitting(false)

    if (!result.success) {
      const { unmatched } = applyFieldErrors(
        setError as SetFieldError,
        result.fieldErrors,
        knownFieldNames
      )
      const remainingMessage = result.message ?? Object.values(unmatched)[0]
      if (remainingMessage) {
        setFormError(remainingMessage)
      }
      return
    }

    onSuccess?.(result.data, data)
  }

  return (
    <form
      className={cn("flex flex-col gap-6", className)}
      onSubmit={handleSubmit(onSubmit)}
      noValidate
    >
      <FieldGroup>
        {visibleFields.map((field) => (
          <ConfigFormField key={field.name} field={field} form={form} />
        ))}
        {formError && (
          <div
            role="alert"
            className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
          >
            {formError}
          </div>
        )}
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? pendingLabel : submitLabel}
        </Button>
      </FieldGroup>
    </form>
  )
}

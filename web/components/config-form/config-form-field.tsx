"use client"

import { Controller, type FieldValues, type Path, type UseFormReturn } from "react-hook-form"

import {
  Field,
  FieldDescription,
  FieldError,
  FieldLabel,
} from "@/components/ui/field"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { ComboboxFieldControl } from "@/components/config-form/combobox-field"
import type { FieldConfig } from "@/lib/forms/types"

/**
 * The shared field-wrapper this ticket's DoD asks for: every field type
 * renders through this one component so label, hint text, and error
 * message look consistent across every config-driven form, instead of each
 * input type re-implementing its own layout (compare `components/ui/field.tsx`,
 * which login-form.tsx / admin-login-form.tsx already use by hand).
 */
export function ConfigFormField<TValues extends FieldValues>({
  field,
  form,
}: {
  field: FieldConfig<TValues>
  form: UseFormReturn<TValues>
}) {
  const {
    control,
    register,
    formState: { errors },
  } = form
  const name = field.name as Path<TValues>
  const error = errors[field.name] as { message?: string } | undefined
  const invalid = !!error
  // KOZ-19 rework: `hint` defaults to rendering under the control (existing
  // behaviour); `hintPlacement: "belowLabel"` moves it under the top-level
  // label, before the control, instead.
  const hintBelowLabel = field.hint && field.hintPlacement === "belowLabel" && !error
  const hintBelowControl = field.hint && field.hintPlacement !== "belowLabel" && !error

  // KOZ-19 rework: checkbox now follows the same label-above-control
  // structure as every other field type (top-level label, optional hint,
  // then the control) instead of the old horizontal control-beside-label
  // layout — the only difference from the generic branch below is that the
  // control itself is wrapped in its own label carrying the option text
  // beside the checkbox (mirroring how `radio` wraps each `RadioGroupItem`).
  if (field.type === "checkbox") {
    const optionText = field.optionLabel ?? field.label
    return (
      <Field data-invalid={invalid}>
        <FieldLabel htmlFor={field.name}>{field.label}</FieldLabel>
        {hintBelowLabel && <FieldDescription>{field.hint}</FieldDescription>}
        <FieldLabel htmlFor={field.name} className="font-normal">
          {renderControl()}
          {optionText}
        </FieldLabel>
        {hintBelowControl && <FieldDescription>{field.hint}</FieldDescription>}
        <FieldError errors={[error]} />
      </Field>
    )
  }

  return (
    <Field data-invalid={invalid}>
      <FieldLabel htmlFor={field.name}>{field.label}</FieldLabel>
      {hintBelowLabel && <FieldDescription>{field.hint}</FieldDescription>}
      {renderControl()}
      {hintBelowControl && <FieldDescription>{field.hint}</FieldDescription>}
      <FieldError errors={[error]} />
    </Field>
  )

  function renderControl() {
    switch (field.type) {
      case "text":
      case "email":
      case "password":
        return (
          <Input
            id={field.name}
            type={field.type}
            placeholder={field.placeholder}
            autoComplete={field.autoComplete}
            disabled={field.disabled}
            aria-invalid={invalid}
            {...register(name)}
          />
        )

      case "number":
        return (
          <Input
            id={field.name}
            type="number"
            placeholder={field.placeholder}
            disabled={field.disabled}
            aria-invalid={invalid}
            {...register(name, { valueAsNumber: true })}
          />
        )

      case "textarea":
        return (
          <Textarea
            id={field.name}
            placeholder={field.placeholder}
            rows={field.rows}
            disabled={field.disabled}
            aria-invalid={invalid}
            {...register(name)}
          />
        )

      case "checkbox":
        return (
          <Controller
            name={name}
            control={control}
            render={({ field: rhf }) => (
              <Checkbox
                id={field.name}
                checked={!!rhf.value}
                onCheckedChange={(checked) => rhf.onChange(!!checked)}
                disabled={field.disabled}
                aria-invalid={invalid}
              />
            )}
          />
        )

      case "select":
        return (
          <Controller
            name={name}
            control={control}
            render={({ field: rhf }) => (
              <Select
                value={(rhf.value as string) ?? ""}
                onValueChange={rhf.onChange}
                disabled={field.disabled}
              >
                <SelectTrigger id={field.name} aria-invalid={invalid} className="w-full">
                  <SelectValue placeholder={field.placeholder} />
                </SelectTrigger>
                <SelectContent>
                  {field.options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        )

      case "radio":
        return (
          <Controller
            name={name}
            control={control}
            render={({ field: rhf }) => (
              <RadioGroup
                aria-invalid={invalid}
                value={(rhf.value as string) ?? ""}
                onValueChange={rhf.onChange}
                disabled={field.disabled}
              >
                {field.options.map((option) => {
                  const optionId = `${field.name}-${option.value}`
                  return (
                    <FieldLabel key={option.value} htmlFor={optionId} className="font-normal">
                      <RadioGroupItem
                        id={optionId}
                        value={option.value}
                        aria-invalid={invalid}
                      />
                      {option.label}
                    </FieldLabel>
                  )
                })}
              </RadioGroup>
            )}
          />
        )

      case "combobox":
        return (
          <Controller
            name={name}
            control={control}
            render={({ field: rhf }) => (
              <ComboboxFieldControl
                id={field.name}
                field={field}
                value={rhf.value as string | string[] | undefined}
                onChange={rhf.onChange}
                invalid={invalid}
              />
            )}
          />
        )

      default:
        return null
    }
  }
}

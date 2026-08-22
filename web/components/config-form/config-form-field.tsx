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

  return (
    <Field data-invalid={invalid}>
      <FieldLabel htmlFor={field.name}>{field.label}</FieldLabel>
      {renderControl()}
      {field.hint && !error && <FieldDescription>{field.hint}</FieldDescription>}
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

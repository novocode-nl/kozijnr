"use client"

import { useTranslation } from "react-i18next"

import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
  ComboboxValue,
  useComboboxAnchor,
} from "@/components/ui/combobox"
import type { ComboboxFieldConfig, SelectOption } from "@/lib/forms/types"

/**
 * Renders a `combobox` field, single- or multiple-select depending on
 * `field.multiple`. Bridges react-hook-form's plain string/string[] value
 * with the base-ui Combobox primitive, which selects whole option objects.
 */
export function ComboboxFieldControl<TValues>({
  id,
  field,
  value,
  onChange,
  invalid,
}: {
  id: string
  field: ComboboxFieldConfig<TValues>
  value: string | string[] | undefined
  onChange: (value: string | string[]) => void
  invalid: boolean
}) {
  if (field.multiple) {
    return (
      <MultipleCombobox
        id={id}
        field={field}
        value={Array.isArray(value) ? value : []}
        onChange={onChange}
        invalid={invalid}
      />
    )
  }

  return (
    <SingleCombobox
      id={id}
      field={field}
      value={typeof value === "string" ? value : ""}
      onChange={onChange}
      invalid={invalid}
    />
  )
}

function SingleCombobox<TValues>({
  id,
  field,
  value,
  onChange,
  invalid,
}: {
  id: string
  field: ComboboxFieldConfig<TValues>
  value: string
  onChange: (value: string) => void
  invalid: boolean
}) {
  const { t } = useTranslation()
  const selectedItem = field.options.find((option) => option.value === value) ?? null

  return (
    <Combobox<SelectOption>
      items={field.options}
      value={selectedItem}
      onValueChange={(item) => onChange(item ? item.value : "")}
      isItemEqualToValue={(a, b) => a.value === b.value}
      disabled={field.disabled}
    >
      <ComboboxInput
        id={id}
        placeholder={field.placeholder}
        aria-invalid={invalid}
        showClear
      />
      <ComboboxContent>
        <ComboboxEmpty>{t("common.noResults")}</ComboboxEmpty>
        <ComboboxList>
          {(item: SelectOption) => (
            <ComboboxItem key={item.value} value={item}>
              {item.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  )
}

function MultipleCombobox<TValues>({
  id,
  field,
  value,
  onChange,
  invalid,
}: {
  id: string
  field: ComboboxFieldConfig<TValues>
  value: string[]
  onChange: (value: string[]) => void
  invalid: boolean
}) {
  const { t } = useTranslation()
  const anchor = useComboboxAnchor()
  const selectedItems = field.options.filter((option) => value.includes(option.value))

  return (
    <Combobox<SelectOption, true>
      items={field.options}
      multiple
      value={selectedItems}
      onValueChange={(items) => onChange(items.map((item) => item.value))}
      isItemEqualToValue={(a, b) => a.value === b.value}
      disabled={field.disabled}
    >
      <ComboboxChips ref={anchor} aria-invalid={invalid}>
        <ComboboxValue>
          {(items: SelectOption[]) =>
            items.map((item) => <ComboboxChip key={item.value}>{item.label}</ComboboxChip>)
          }
        </ComboboxValue>
        <ComboboxChipsInput
          id={id}
          placeholder={selectedItems.length > 0 ? undefined : field.placeholder}
        />
      </ComboboxChips>
      <ComboboxContent anchor={anchor}>
        <ComboboxEmpty>{t("common.noResults")}</ComboboxEmpty>
        <ComboboxList>
          {(item: SelectOption) => (
            <ComboboxItem key={item.value} value={item}>
              {item.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  )
}

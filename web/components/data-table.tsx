"use client"

import * as React from "react"
import {
  columnVisibilityFeature,
  createColumnHelper,
  createSortedRowModel,
  rowSortingFeature,
  tableFeatures,
  useTable,
  type ColumnDef,
  type RowData,
  type SortingState,
} from "@tanstack/react-table"
import { ArrowDown, ArrowUp, ArrowUpDown, MoreHorizontal } from "lucide-react"
import { useTranslation } from "react-i18next"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

/**
 * Generic, reusable data table, built on shadcn's Data Table pattern (Table
 * primitives + @tanstack/react-table) so future overviews can reuse it by
 * supplying their own `columns`/`data` rather than each screen hand-rolling
 * its own `<table>` markup.
 *
 * `dataTableFeatures` fixes one shared feature set — row sorting, plus
 * column visibility (required by v9's `row.getVisibleCells()` used for
 * rendering, not exposed as a toggle here) — across every instance of this
 * component. Callers build their `ColumnDef`s via
 * `dataTableColumnHelper<TData>()` so the column and table generics line
 * up, without every call site re-declaring its own `tableFeatures`.
 */
export const dataTableFeatures = tableFeatures({
  rowSortingFeature,
  columnVisibilityFeature,
  sortedRowModel: createSortedRowModel(),
})

export function dataTableColumnHelper<TData extends RowData>() {
  return createColumnHelper<typeof dataTableFeatures, TData>()
}

export type DataTableColumn<TData extends RowData, TValue = unknown> = ColumnDef<
  typeof dataTableFeatures,
  TData,
  TValue
>

/**
 * Builds a sortable column header: a ghost button that toggles ascending/
 * descending sort on click. Shared here so callers don't re-implement the
 * same button + icon per sortable column.
 *
 * `sorted` mirrors TanStack's `column.getIsSorted()` ("asc" | "desc" |
 * `false`) so the icon reflects the column's actual sort state instead of
 * a static placeholder: an up/down arrow for the active direction, or the
 * neutral (muted) up-down arrow when this column isn't the active sort.
 */
export function DataTableSortableHeader({
  label,
  sorted,
  onSort,
}: {
  label: string
  sorted: false | "asc" | "desc"
  onSort: () => void
}) {
  return (
    <Button variant="ghost" className="-ml-3 h-8" onClick={onSort}>
      {label}
      {sorted === "asc" ? (
        <ArrowUp />
      ) : sorted === "desc" ? (
        <ArrowDown />
      ) : (
        <ArrowUpDown className="text-muted-foreground/50" />
      )}
    </Button>
  )
}

/**
 * Per-row "kebab" actions menu: a `MoreHorizontal`-icon trigger opening a
 * `DropdownMenu`. Shared here (rather than hand-rolled per table) so every
 * overview's actions column looks and behaves the same — only the menu's
 * contents (which actions, where they navigate) are table-instance-
 * specific, supplied as `children` (typically `DropdownMenuItem`s) by the
 * caller. This component has no entity-specific knowledge, matching
 * `<DataTable>`'s own "entity-agnostic" design.
 */
export function DataTableRowActions({
  children,
  label = "Acties",
}: {
  children: React.ReactNode
  label?: string
}) {
  return (
    // Stops the click from bubbling up to a `<DataTable onRowClick>` row —
    // otherwise opening/using the kebab menu on a clickable row would also
    // trigger that row's navigation.
    <div onClick={(event) => event.stopPropagation()}>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button variant="ghost" size="icon-sm" aria-label={label}>
              <MoreHorizontal />
            </Button>
          }
        />
        <DropdownMenuContent align="end">{children}</DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}

interface DataTableProps<TData extends RowData> {
  // `TValue` is invariant on `ColumnDef` (used in both input and output
  // position), so a heterogeneous column array — the whole point of this
  // being generic — can't be typed with a shared concrete `TValue`. `any`
  // here (matching shadcn's own data-table-demo) is the escape hatch.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  columns: DataTableColumn<TData, any>[]
  data: TData[]
  /**
   * Shown in the body when `data` is empty. Defaults to the generic
   * `common.noResults` translation (see lib/i18n/resources/*.json) so a
   * caller that doesn't supply one still gets a locale-aware message
   * instead of a hardcoded string — this component has no entity-specific
   * knowledge, so it can't offer a more specific default itself. Callers
   * with entity-specific copy (e.g. `<TenantsTable>`'s "Geen tenants
   * gevonden.") should keep passing their own translated string.
   */
  emptyMessage?: string
  /**
   * When given, the whole row becomes clickable (e.g. navigate to a detail
   * page). A click inside `<DataTableRowActions>` never reaches this — it
   * stops its own propagation — so the kebab menu and row-click coexist.
   */
  onRowClick?: (row: TData) => void
}

export function DataTable<TData extends RowData>({
  columns,
  data,
  emptyMessage,
  onRowClick,
}: DataTableProps<TData>) {
  const { t } = useTranslation()
  const [sorting, setSorting] = React.useState<SortingState>([])
  const resolvedEmptyMessage = emptyMessage ?? t("common.noResults")

  const table = useTable({
    features: dataTableFeatures,
    data,
    columns,
    onSortingChange: setSorting,
    state: { sorting },
  })

  return (
    <div className="overflow-hidden rounded-md border">
      <Table>
        <TableHeader>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id}>
                  {header.isPlaceholder ? null : (
                    <table.FlexRender header={header} />
                  )}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {table.getRowModel().rows?.length ? (
            table.getRowModel().rows.map((row) => (
              <TableRow
                key={row.id}
                onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                className={cn(onRowClick && "cursor-pointer")}
              >
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    <table.FlexRender cell={cell} />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : (
            <TableRow>
              <TableCell
                colSpan={columns.length}
                className="h-24 text-center text-muted-foreground"
              >
                {resolvedEmptyMessage}
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  )
}

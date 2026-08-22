"use client"

import * as React from "react"

import {
  DataTable,
  DataTableSortableHeader,
  dataTableColumnHelper,
} from "@/components/data-table"
import { listTenants, type TenantSummary } from "@/lib/api"

/**
 * Tenant overview table: a thin, tenant-specific wrapper around the
 * generic `<DataTable>` — this is the only file that knows about
 * `TenantSummary`, keeping components/data-table.tsx entity-agnostic for
 * later overviews to reuse.
 *
 * Data is fetched client-side via lib/api.ts's `listTenants`, same as
 * every other backend call this frontend makes (see that file's doc
 * comment on why: cross-origin, cookie-credentialed calls straight from
 * the browser to api.<base>).
 */
const columnHelper = dataTableColumnHelper<TenantSummary>()

const dateFormatter = new Intl.DateTimeFormat("nl-NL", {
  dateStyle: "medium",
  timeStyle: "short",
})

const columns = [
  columnHelper.accessor("subdomain", {
    header: ({ column }) => (
      <DataTableSortableHeader
        label="Subdomain"
        sorted={column.getIsSorted()}
        onSort={() => column.toggleSorting()}
      />
    ),
    cell: ({ row }) => (
      <span className="font-medium">{row.original.subdomain}</span>
    ),
  }),
  columnHelper.accessor("createdAt", {
    header: ({ column }) => (
      <DataTableSortableHeader
        label="Aangemaakt op"
        sorted={column.getIsSorted()}
        onSort={() => column.toggleSorting()}
      />
    ),
    cell: ({ row }) => dateFormatter.format(new Date(row.original.createdAt)),
  }),
]

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; tenants: TenantSummary[] }

export function TenantsTable() {
  const [state, setState] = React.useState<LoadState>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    listTenants()
      .then((tenants) => {
        if (!cancelled) {
          setState({ status: "loaded", tenants })
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: "error" })
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  if (state.status === "error") {
    return (
      <p className="text-sm text-destructive">
        Kon de tenants niet laden. Probeer de pagina te verversen.
      </p>
    )
  }

  return (
    <DataTable
      columns={columns}
      data={state.status === "loaded" ? state.tenants : []}
      emptyMessage={
        state.status === "loading" ? "Tenants laden..." : "Geen tenants gevonden."
      }
    />
  )
}

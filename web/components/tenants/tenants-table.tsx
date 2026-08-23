"use client"

import * as React from "react"
import { useRouter } from "next/navigation"

import {
  DataTable,
  DataTableRowActions,
  DataTableSortableHeader,
  dataTableColumnHelper,
} from "@/components/data-table"
import { DropdownMenuItem } from "@/components/ui/dropdown-menu"
import { listTenants, type TenantSummary } from "@/lib/api"
import { ArchiveTenantDialog } from "@/components/tenants/archive-tenant-dialog"

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
 *
 * `showArchived` is set once by the parent (`TenantsPage`'s active/archived
 * tab) and never flips within this component's lifetime — see that page's
 * comment on why each tab remounts its own `<TenantsTable>` instance
 * instead of one instance toggling a boolean.
 *
 * Row actions (KOZ-27 rework, functional review): the kebab menu offers
 * "Bekijken" (navigate to the detail page) instead of "Bewerken" — editing
 * now lives on the detail page's own "Bewerken" button next to its kebab
 * menu, not as a row action here. The row itself is still fully clickable
 * to the same detail page, unchanged from before.
 */
const dateFormatter = new Intl.DateTimeFormat("nl-NL", {
  dateStyle: "medium",
  timeStyle: "short",
})

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; tenants: TenantSummary[] }

interface TenantsTableProps {
  showArchived: boolean
}

/**
 * Renders one active/archived view of the tenant list. The parent
 * (`TenantsPage`) mounts one `<TenantsTable>` per tab (active/archived) and
 * remounts it (via a refresh-token `key`) whenever that tab's data should
 * refetch from scratch — see that page's comment — rather than this
 * component resetting its own state mid-life, which the project's lint
 * rules disallow (no synchronous `setState` in an effect body).
 */
export function TenantsTable({ showArchived }: TenantsTableProps) {
  const router = useRouter()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [archivingTenant, setArchivingTenant] = React.useState<TenantSummary | null>(null)

  React.useEffect(() => {
    let cancelled = false

    listTenants(showArchived)
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
  }, [showArchived])

  function upsertTenant(tenant: TenantSummary) {
    setState((current) => {
      if (current.status !== "loaded") {
        return current
      }

      // The updated tenant might have just left this view (renamed away,
      // or its archived status no longer matches `showArchived`) — drop it
      // from the list in that case rather than showing stale data.
      const belongsInThisView = tenant.archived === showArchived
      const withoutTenant = current.tenants.filter((existing) => existing.subdomain !== tenant.subdomain)

      return {
        status: "loaded",
        tenants: belongsInThisView ? [...withoutTenant, tenant] : withoutTenant,
      }
    })
  }

  const columns = React.useMemo(() => {
    const columnHelper = dataTableColumnHelper<TenantSummary>()

    return [
      columnHelper.accessor("name", {
        header: ({ column }) => (
          <DataTableSortableHeader label="Naam" sorted={column.getIsSorted()} onSort={() => column.toggleSorting()} />
        ),
        cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
      }),
      columnHelper.accessor("subdomain", {
        header: ({ column }) => (
          <DataTableSortableHeader
            label="Subdomain"
            sorted={column.getIsSorted()}
            onSort={() => column.toggleSorting()}
          />
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
      columnHelper.display({
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <DataTableRowActions label={`Acties voor ${row.original.subdomain}`}>
              <DropdownMenuItem
                onClick={() => router.push(`/tenants/${encodeURIComponent(row.original.subdomain)}`)}
              >
                Bekijken
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setArchivingTenant(row.original)}>
                {row.original.archived ? "Dearchiveren" : "Archiveren"}
              </DropdownMenuItem>
            </DataTableRowActions>
          </div>
        ),
      }),
    ]
  }, [router])

  if (state.status === "error") {
    return (
      <p className="text-sm text-destructive">
        Kon de tenants niet laden. Probeer de pagina te verversen.
      </p>
    )
  }

  return (
    <>
      <DataTable
        columns={columns}
        data={state.status === "loaded" ? state.tenants : []}
        emptyMessage={
          state.status === "loading"
            ? "Tenants laden..."
            : showArchived
              ? "Geen gearchiveerde tenants gevonden."
              : "Geen tenants gevonden."
        }
        onRowClick={(tenant) => router.push(`/tenants/${encodeURIComponent(tenant.subdomain)}`)}
      />
      <ArchiveTenantDialog
        key={archivingTenant?.subdomain ?? "none"}
        open={archivingTenant !== null}
        onOpenChange={(open) => {
          if (!open) setArchivingTenant(null)
        }}
        tenant={archivingTenant}
        onChanged={(tenant) => {
          upsertTenant(tenant)
          setArchivingTenant(null)
        }}
      />
    </>
  )
}

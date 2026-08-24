"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { DataTable, dataTableColumnHelper } from "@/components/data-table"
import { listAdminUsers, type AdminUserSummary } from "@/lib/api"

/**
 * Admin user overview table (KOZ-30): a thin, entity-specific wrapper
 * around the generic `<DataTable>`, mirroring `<TenantsTable>`. No row
 * actions (view/edit/delete) — editing/removing existing admin users is out
 * of scope for KOZ-30.
 *
 * Data is fetched client-side via lib/api.ts's `listAdminUsers`, same as
 * every other backend call this frontend makes.
 *
 * The parent (`UsersPage`) remounts this component (via a refresh-token
 * `key`) after creating a user, so this component never needs to manage its
 * own refetch trigger.
 */
type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; users: AdminUserSummary[] }

export function AdminUsersTable() {
  const { t } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    listAdminUsers()
      .then((users) => {
        if (!cancelled) {
          setState({ status: "loaded", users })
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

  const columns = React.useMemo(() => {
    const columnHelper = dataTableColumnHelper<AdminUserSummary>()

    return [
      columnHelper.accessor("email", {
        header: t("users.columnEmail"),
        cell: ({ row }) => <span className="font-medium">{row.original.email}</span>,
      }),
      columnHelper.accessor("roles", {
        header: t("users.columnRoles"),
        cell: ({ row }) => row.original.roles.join(", "),
      }),
    ]
  }, [t])

  if (state.status === "error") {
    return <p className="text-sm text-destructive">{t("users.loadError")}</p>
  }

  return (
    <DataTable
      columns={columns}
      data={state.status === "loaded" ? state.users : []}
      emptyMessage={state.status === "loading" ? t("users.loadingRows") : t("users.emptyActive")}
    />
  )
}

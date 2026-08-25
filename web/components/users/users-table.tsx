"use client"

import { useTranslation } from "react-i18next"

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { roleLabel } from "@/lib/i18n/role-labels"
import type { TenantUserSummary } from "@/lib/api"

/**
 * The email + roles table both tenant-user lists (own-users-page and the
 * admin detail page's users tab) render — extracted verbatim so the two
 * stay identical by construction.
 */
export function UsersTable({ users }: { users: TenantUserSummary[] }) {
  const { t } = useTranslation()

  return (
    <div className="overflow-hidden rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t("users.columnEmail")}</TableHead>
            <TableHead>{t("users.columnRoles")}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {users.map((user) => (
            <TableRow key={user.email}>
              <TableCell className="font-medium">{user.email}</TableCell>
              <TableCell className="text-muted-foreground">
                {user.roles.map((role) => roleLabel(role, t)).join(", ")}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

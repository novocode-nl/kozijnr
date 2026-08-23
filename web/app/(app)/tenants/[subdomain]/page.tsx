"use client"

import * as React from "react"
import { useParams, useRouter } from "next/navigation"
import { MoreHorizontal } from "lucide-react"

import { PageHeading } from "@/components/page-heading"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { TenantFormDialog } from "@/components/tenants/tenant-form-dialog"
import { ArchiveTenantDialog } from "@/components/tenants/archive-tenant-dialog"
import { TenantUsersTab } from "@/components/tenants/tenant-users-tab"
import { getTenant, type TenantSummary } from "@/lib/api"
import { contextLabel } from "@/lib/navigation/menu-config"

const dateFormatter = new Intl.DateTimeFormat("nl-NL", { dateStyle: "medium", timeStyle: "short" })

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "not-found" }
  | { status: "loaded"; tenant: TenantSummary }

/**
 * Tenant detail page (KOZ-27): tenant header + a generic, extensible
 * `<Tabs>` structure. This ticket only adds the "Gebruikers" tab; later
 * tickets add more tabs alongside it without needing to touch the
 * surrounding page structure.
 *
 * Rework (KOZ-27 functional review): "Bewerken" is its own button next to
 * the kebab menu, not a kebab item — the kebab now only holds
 * archive/dearchive. Both dialogs (`TenantFormDialog`, `ArchiveTenantDialog`)
 * are fully controlled, same components used elsewhere.
 */
/**
 * Thin wrapper keying the actual page content on `subdomain`: navigating
 * from one tenant's detail page to another's re-uses the same route
 * component (Next.js doesn't remount it just because a dynamic segment
 * changed), so without this key the content below would need to reset its
 * own "loading" state mid-life inside an effect — disallowed by this
 * project's lint rules. Keying here makes that reset a natural remount
 * instead.
 */
export default function TenantDetailPage() {
  const params = useParams<{ subdomain: string }>()
  const subdomain = decodeURIComponent(params.subdomain)

  return <TenantDetailPageContent key={subdomain} subdomain={subdomain} />
}

function TenantDetailPageContent({ subdomain }: { subdomain: string }) {
  const router = useRouter()

  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [editOpen, setEditOpen] = React.useState(false)
  const [archiveOpen, setArchiveOpen] = React.useState(false)

  React.useEffect(() => {
    let cancelled = false

    getTenant(subdomain)
      .then((tenant) => {
        if (!cancelled) {
          setState(tenant ? { status: "loaded", tenant } : { status: "not-found" })
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
  }, [subdomain])

  if (state.status === "loading") {
    return (
      <div className="flex flex-1 flex-col gap-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  if (state.status === "not-found") {
    return <p className="text-sm text-muted-foreground">Deze tenant bestaat niet (meer).</p>
  }

  if (state.status === "error") {
    return (
      <p className="text-sm text-destructive">
        Kon de tenant niet laden. Probeer de pagina te verversen.
      </p>
    )
  }

  const tenant = state.tenant

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={tenant.name}
        description={
          `${tenant.subdomain} · aangemaakt op ${dateFormatter.format(new Date(tenant.createdAt))}`
          + (tenant.archived ? " · gearchiveerd" : "")
        }
        breadcrumbs={[
          { label: contextLabel.admin, href: "/" },
          { label: "Tenants", href: "/tenants" },
          { label: tenant.name },
        ]}
        actions={
          <>
            <Button onClick={() => setEditOpen(true)}>
              Bewerken
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger
                render={
                  <Button variant="outline" size="icon-sm" aria-label={`Acties voor ${tenant.subdomain}`}>
                    <MoreHorizontal />
                  </Button>
                }
              />
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => setArchiveOpen(true)}>
                  {tenant.archived ? "Dearchiveren" : "Archiveren"}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </>
        }
      />
      <Tabs defaultValue="users">
        <TabsList>
          <TabsTrigger value="users">Gebruikers</TabsTrigger>
        </TabsList>
        <TabsContent value="users">
          <TenantUsersTab subdomain={tenant.subdomain} />
        </TabsContent>
      </Tabs>
      <TenantFormDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        tenant={tenant}
        onUpdated={(updated) => {
          setState({ status: "loaded", tenant: updated })
          if (updated.subdomain !== subdomain) {
            router.replace(`/tenants/${encodeURIComponent(updated.subdomain)}`)
          }
        }}
      />
      <ArchiveTenantDialog
        open={archiveOpen}
        onOpenChange={setArchiveOpen}
        tenant={tenant}
        onChanged={(updated) => setState({ status: "loaded", tenant: updated })}
      />
    </div>
  )
}

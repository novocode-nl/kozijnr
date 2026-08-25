import { describe, expect, it, vi } from "vitest"

import contract from "./shared-constants.json"
import { apiBaseUrl } from "@/lib/api-base-url"
import { DEFAULT_LOCALE, SUPPORTED_LOCALES } from "@/lib/i18n/locale"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"

// app-context.ts imports next/headers for its server-only helper; stub it so
// the pure resolveAppContext stays importable in this node test environment.
vi.mock("next/headers", () => ({ headers: () => new Map() }))
const { resolveAppContext } = await import("@/lib/context/app-context")

/**
 * Counterpart of api/tests/Shared/Contract/SharedConstantsContractTest.php:
 * both sides assert their own constants against the same contract JSON
 * (kept byte-identical across the three copies by scripts/check-contracts.mjs),
 * so a change on either side fails loudly instead of silently drifting.
 */
describe("shared-constants contract", () => {
  it("mirrors the supported locales and default", () => {
    expect([...SUPPORTED_LOCALES]).toEqual(contract.locales.supported)
    expect(DEFAULT_LOCALE).toBe(contract.locales.default)
  })

  it("mirrors the tenant-user role constants", () => {
    expect(TenantUser.ROLE_TENANT_ADMIN).toBe(contract.tenantUserRoles.admin)
    expect(TenantUser.ROLE_TENANT_USER).toBe(contract.tenantUserRoles.default)
  })

  it("mirrors the token cookie name", () => {
    expect(TENANT_TOKEN_COOKIE_NAME).toBe(contract.cookies.tenantApiToken)
  })

  it("treats the contract's reserved admin label as the admin context", () => {
    expect(resolveAppContext(`${contract.reservedSubdomains.admin}.kozijnr.localhost`)).toBe("admin")
  })

  it("targets the contract's reserved api label as the API host", () => {
    expect(apiBaseUrl("tenant1.kozijnr.localhost")).toBe(
      `http://${contract.reservedSubdomains.api}.kozijnr.localhost`
    )
  })
})

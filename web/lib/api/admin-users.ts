import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { ActionResult } from "@/lib/forms/types"

import { getJsonOrThrow, requestAction } from "./http"

/**
 * Read-model shape returned by GET /api/admin/users and the create endpoint
 * (UserSummary::toArray) — the admin user overview (KOZ-30).
 */
export type AdminUserSummary = {
  email: string
  roles: string[]
}

/** Generated admin-user credentials, included once in a successful createAdminUser response. */
export type AdminUserCredentials = {
  email: string
  password: string
}

export type CreatedAdminUser = AdminUserSummary & { password: string }

/** Payload shape CreateAdminUserController expects. */
export type CreateAdminUserPayload = { email: string }

function adminUserCreateFailedMessage(): string {
  return translate("users.error.createFailed", getClientLocale()) ?? "Failed to create admin user."
}

/**
 * Admin user overview (KOZ-30): GET /api/admin/users, guarded backend-side
 * by the `user:list` permission (see ListAdminUsersController).
 */
export async function listAdminUsers(): Promise<AdminUserSummary[]> {
  return getJsonOrThrow("/api/admin/users", "admin users")
}

/**
 * Admin user creation (KOZ-30): POST /api/admin/users, guarded backend-side
 * by the `user:create` permission (see CreateAdminUserController). Every
 * user created this way gets ROLE_SUPER_ADMIN and a generated password,
 * returned once in `data.password` — same one-time-credentials pattern as
 * `createTenant`'s `tenantAdmin` block. Failures (invalid/duplicate email)
 * are attached to the `email` field so `<ConfigForm>` shows them in the
 * right place.
 */
export async function createAdminUser(payload: CreateAdminUserPayload): Promise<ActionResult<CreatedAdminUser>> {
  return requestAction("/api/admin/users", {
    json: payload,
    fallbackMessage: adminUserCreateFailedMessage,
    errorField: "email",
  })
}

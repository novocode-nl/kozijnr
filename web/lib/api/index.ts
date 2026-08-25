/**
 * Browser-side service layer over the REST API on api.<base>, split by
 * domain around the shared request core in ./http. `@/lib/api` resolves
 * here, so every pre-split import keeps working unchanged.
 */
export * from "./auth"
export * from "./tenants"
export * from "./tenant-users"
export * from "./admin-users"
export * from "./tenant-settings"
export * from "./profile-photo"

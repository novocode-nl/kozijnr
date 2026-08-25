# Nulmeting duplicatie & opschoning — 2026-08-25

Onderzoeksrapport (architect-agent, geverifieerd tegen `main` @ `d8dcf1f`) als basis voor het
consolidatieplan in `docs/superpowers/plans/2026-08-25-dedup-consolidation.md`.

## Kernconclusie

De splitsing `User` (public schema, admin-realm) vs `TenantUser` (tenant-schema) is een **bewuste,
verdedigbare ontwerpkeuze**: schema-per-tenant maakt cross-tenant toegang structureel onmogelijk
(tabellen bestaan simpelweg niet in elkaars schema). De entiteiten zelf worden dus **niet**
samengevoegd. De echte duplicatie zit in de laag eromheen (poorten, adapters, controllers,
frontend-client) en in het onbewaakte impliciete contract tussen backend en frontend.

## Hoe het draait (relevant voor uitvoering)

- Monorepo: `api/` (Symfony, PHP >= 8.4.1), `web/` (Next.js 16, Node 24), docker-compose,
  gedeelde nginx-proxy op :80. Container-mounts: backend ziet **alleen** `./api`, frontend
  **alleen** `./web` — cross-tree file-reads werken lokaal dus niet vanuit testcontainers.
- Per-ticket worktree: branch **moet** `koz-<n>` heten (`make ensure-env` faalt anders luid);
  testdomein `koz-<n>.kozijnr.localhost`, DB-poort `5432+n`.
- Commando's: `make up`, `make seed`, `make test-backend [args=…]`, `make npm args="run test"`,
  `make npm args="run lint"`. Frontend-scripts: `lint` = eslint, `test` = `vitest run`, `build` = next build.
- Backend-tests draaien tegen een **aparte testdatabase `app_test`** (doctrine.yaml `when@test`:
  `dbname_suffix: '_test...'`), niet tegen de dev-DB `app`. Niets maakt of migreert `app_test`
  automatisch (`make seed` migreert alleen `app`); op een verse worktree-DB bestaat hij niet.
  Tests maken zelf throwaway-tenant-schemas aan en doen o.a. `DELETE FROM public.tenants`.
- **Er is géén CI** (geen `.github/`), en het >85%-coverage-mandaat is nergens technisch verankerd
  (geen pcov/xdebug in het image, geen coverage-scripts).
- CQRS = één invokable use-case-klasse per command/query, direct in single-action controllers.
  **Geen Messenger.** Poorten worden handmatig gebonden in `api/config/services.yaml`.
- Auth: admin = stateful sessie + json_login (`/api/admin/*`); tenant = stateless opaque token in
  HttpOnly cookie `tenant_api_token` (lookup in actief tenant-schema, sliding expiry 30d).

## Duplicatie-inventaris

### (a) Backend-intern

| # | Wat | Waar | Risico |
|---|-----|------|--------|
| a1 | Dubbele hasher-poort + adapter (zelfde patroon; User-variant heeft alleen `hash()`, TenantUser-variant `hash()`+`verify()`) | `User/Domain/PasswordHasherInterface` + `TenantUser/Domain/PasswordHasherInterface`, beide `Infrastructure/Security/SymfonyPasswordHasher` | laag |
| a2 | Placeholder-hash-constructiepatroon 2× | `CreateSuperAdmin.php:45-50`, `CreateTenantUser.php:35-40` | laag, load-bearing |
| a3 | `bin2hex(random_bytes(12))` 3× + `filter_var(FILTER_VALIDATE_EMAIL)` 3× | `CreateAdminUser.php:32,39`, `CreateTenantUserForCurrentTenant.php:45,59`, `ProvisionTenantWithAdmin.php:47,56` | zeer laag (errorKeys per context verschillen, moeten parametriseerbaar blijven) |
| a4 | `SET search_path`-boilerplate op ±10 plekken (switch-try-finally of losse reset) | `ListTenantUsers`, `CreateTenantUserForTenant`, `ProvisionTenantWithAdmin`, `SeedDevFixturesCommand:136-162`, `CreateTenantUserCommand:71-93`; resets in `ArchiveTenant`/`UnarchiveTenant`/`UpdateTenant`/`ProvisionTenant`; canoniek in `TenantResolverListener` + `TenantSchemaMigrator` | **security-kritiek** — dit ís de tenant-isolatie; per call-site migreren met integratietests als vangnet |
| a5 | Route-guard-tweeling | `AdminRouteGuardListener` vs `TenantRouteGuardListener` (prefixlijst r24-35 is zelf onderhoudsrisico) | laag |
| a6 | Upload/serve-logica foto vs login-image (mime-allowlist, 5MiB, extensie-maps, storage-key-patroon) | `UploadProfilePhoto` vs `UploadTenantLoginImage`; `GetProfilePhoto` vs `GetTenantLoginImage` | laag-middel |
| a7 | Controller-tweeling profielfoto (grootste letterlijke copy-paste) | `UploadProfilePhotoController` vs `UploadTenantProfilePhotoController`; `GetProfilePhotoController` vs `GetTenantProfilePhotoController` — verschil is alleen user-resolutie per firewall | middel |
| a8 | Handmatige `json_decode`-payload-extractie in elke schrijvende controller | Login/Create*-controllers | laag |
| a9 | DTO-tweeling `UserSummary` vs `TenantUserSummary` | beide `{email, roles}` | triviaal, maar koppelt API-contracten |
| a10 | Repository-tweeling | `DoctrineUserRepository` vs `DoctrineTenantUserRepository` | lage winst |
| a11 | Dode/skelet-code | lege mappen `api/src/Entity/`, `api/src/Repository/`; admin-endpoints `/api/admin/me/profile-photo` zonder frontend-aanroeper (bewust, KOZ-32/33) | — |

### (b) Frontend-intern

| # | Wat | Waar |
|---|-----|------|
| b1 | `web/lib/api.ts` (749 r.): try-fetch-catch → `!ok` → `apiErrorMessage` → `ActionResult` ±10× uitgeschreven; "throw on !ok"-GET's 5×; drie privé-helpers die zelf ook varianten zijn | `createTenantUser`, `createOwnTenantUser` ("Mirrors createTenantUser"), `createAdminUser`, `updateTenantDefaultLocale`, `uploadTenantLoginImage`, `postTenantAction`, `submitTenantPayload`, `postCredentials`; `listTenants`/`listTenantUsers`/`listAdminUsers`/`listOwnTenantUsers`/`getTenantSettings` |
| b2 | Drie credentials-dialogen (diff = type-import + i18n-keys + één rol-regel) | `admin-user-credentials-dialog.tsx`, `tenant-admin-credentials-dialog.tsx`, `tenant-user-credentials-dialog.tsx` |
| b3 | Twee form-dialogen, zelfde Dialog+ConfigForm-skelet | `admin-user-form-dialog.tsx` (hardcodet action), `tenant-user-form-dialog.tsx` (al gegeneraliseerd via action-prop) |
| b4 | 3× zelfde users-lijst: `LoadState`-union + `useEffect`+`cancelled`-fetch + Skeleton/Table-markup + credentials-wiring | `own-users-page.tsx`, `tenant-users-tab.tsx`, (`admin-users-page.tsx` deelt dialog-wiring) |
| b5 | Login-formulieren (tenant-variant heeft échte extra semantiek: locale-switch + login-image) | `login-form.tsx` vs `admin-login-form.tsx` |
| b6 | Klein grut: eager `("nl")`-schema-exports, demo-code `app/demo/` | — |

Er zijn **geen tests** voor `lib/api.ts`; Vitest test uitsluitend `web/lib/**` (node-env, geen
component-tests, geen e2e).

### (c) Backend↔frontend-spiegels (impliciete contracten)

| # | Wat | Backend | Frontend |
|---|-----|---------|----------|
| c1 | Locales `['nl','en']` + default `nl` | `Tenant::SUPPORTED_LOCALES`/`DEFAULT_LOCALE` | `lib/i18n/locale.ts` |
| c2 | Rol-constanten | `TenantUser::ROLE_TENANT_ADMIN`/`DEFAULT_ROLE` | `lib/domain/tenant-user-roles.ts` |
| c3 | Cookie-naam `tenant_api_token` (+ lifetime 30d dubbel) | `TenantApiTokenCookie::NAME` | `lib/auth/token-cookie.ts` |
| c4 | Reserved subdomeinen `admin`/`api` | `Subdomain::RESERVED_ADMIN/RESERVED_API` | `lib/context/app-context.ts` |
| c5 | Tenant-validatieregels (slug-regex, maxlengtes) | `TenantName` + `Tenant` | `lib/schemas/tenant.ts` |
| c6 | **ErrorKey-catalogus** (grootste stille contract: hernoemde key degradeert stil naar Engelse fallback) | alle `ValidationException::create(...)`/`ExceptionResponsePayload::withKey(...)`-keys, patroon `<domein>.error.<naam>` | `lib/i18n/resources/{nl,en}.json` |
| c7 | Response-DTO-types handmatig naspiegeld | `*Summary::toArray()` e.d. | types in `lib/api.ts` |

Voor c7 is OpenAPI+codegen overwogen en **afgewezen** voor nu (±20 endpoints; codegen-churn,
form-schema's ≠ API-schema's). Lichte optie gekozen: contract-checks (zie plan).

## Risico's / randvoorwaarden

1. Tenant-isolatie hangt aan drie mechanismen: search_path-switch (resolver), schema-lokaliteit van
   `tenant_users`/tokens, Origin-gebaseerde resolutie op `api.<base>`. Item a4 raakt dit direct.
2. Auth-flows zijn bewust verschillend (sessie vs token); `AdminLoginController` is een bewuste
   placeholder, géén duplicaat.
3. Migraties: permissienamen zijn data én code; niets hernoemen zonder migratie in beide sets.
4. Seed-fixtures (`tenant@kozijnr.nl` = ROLE_TENANT_ADMIN sinds KOZ-34) zijn een afhankelijkheid.
5. ErrorKey-contract is onbewaakt → eerst contract-check bouwen, dán refactoren.
6. Cookie-/CORS-/ORB-subtiliteiten rond `apiBaseUrl`, login-image (fetch+blob i.p.v. `<img>`).
7. `TenantRouteGuardListener`-prefixlijst moet bij routeverplaatsingen mee.
8. Geen CI → eerst dichten; elke opschoning leunt anders op lokaal draaien van beide suites.

## Scopebesluiten (voorstel, ter review)

**Doen:** CI-workflow; contract-checks (c1-c4, c6); frontend request-kern + opsplitsen `lib/api.ts`
(b1); credentials-dialoog-unificatie (b2); `useLoadState` + gedeelde users-tabel (b4); gedeelde
hasher-poort (a1); `GeneratedPassword` + e-mailvalidatiehelper (a3); tenant-schema-context-service
(a4); gedeelde image-policy (a6); profielfoto-endpoint-handler (a7); lege skelet-mappen weg (a11).

**Niet doen (bewust):** entity-merge User/TenantUser; login-formulieren samenvoegen (b5, echte
semantische verschillen); form-dialogen samenvoegen (b3, al dunne config-wrappers — YAGNI);
DTO-/repository-tweelingen (a9/a10, koppelt contracten resp. lage winst); route-guard-merge (a5,
klein en het echte risico — de prefixlijst — verdwijnt er niet door); `#[MapRequestPayload]` (a8,
apart traject, raakt elk endpoint-contract); OpenAPI-codegen (c7); verwijderen admin-profielfoto-
endpoints en `app/demo/` (bewuste keuzes uit KOZ-32/33 resp. dev-tooling — besluit bij de mens);
sliding-expiry-perf; `getTenant`-endpoint (API-uitbreiding, geen refactor); coverage-drempel
afdwingen (follow-up-ticket: pcov in image + drempel in CI).

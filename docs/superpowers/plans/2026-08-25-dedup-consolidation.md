# Dedup & Consolidatie Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De geïnventariseerde duplicatie tussen en binnen backend en frontend samenvoegen, met contract-checks en CI als vangnet, zonder de tenant-isolatie of API-contracten te wijzigen.

**Architecture:** Pure refactor + "simpele extra checks": geen endpoint-, schema- of gedragswijzigingen. Backend: gedeelde poorten/policies in `App\Shared`, één tenant-schema-context-service. Frontend: één request-kern onder `web/lib/api/`, gedeelde dialoog/hook/tabel-componenten. Repo-breed: contract-checkscript + GitHub Actions CI.

**Tech Stack:** Symfony/PHP >= 8.4, PHPUnit 13; Next.js 16/React 19, Vitest 4, Node 24; GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-25-dedup-nulmeting.md`

## Global Constraints

- Branch **moet** `koz-<n>` heten (Asana-ticket eerst aanmaken); werk in een git worktree; testdomein `koz-<n>.kozijnr.localhost`.
- API-contracten (paden, payloads, responses, statuscodes), errorKeys en i18n-catalogi blijven **byte-voor-byte ongewijzigd** — dit is een refactor.
- Geen nieuwe runtime-dependencies backend; frontend alleen devDependencies (`@testing-library/react`, `jsdom`).
- Backend-tests: `make test-backend` (= `php bin/phpunit` met `APP_ENV=test` in de container). **Let op: de testsuite draait tegen een aparte database `app_test`** (doctrine.yaml `when@test` plakt `_test` achter de dbname); op een verse worktree bestaat die niet. Setup: `make up && make seed && make test-db` (het `test-db`-target komt uit Taak 1). Frontend: `make npm args="run test"`, `make npm args="run lint"`, en `make npm args="run build"` vóór de PR.
- Containers mounten alleen hun eigen tree (`./api` resp. `./web`): tests in de suites mogen **nooit** cross-tree bestanden lezen. Cross-tree checks lopen via het host/CI-script uit Taak 2.
- Elke taak eindigt met alle bestaande + nieuwe tests groen en een eigen commit (NL commit-message, prefix `KOZ-<n>:`).
- Codecommentaar-stijl van het project volgen: uitgebreide "waarom"-docblocks, Engels.

---

### Taak 1: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`
- Modify: `Makefile` (target `test-db`)

**Interfaces:**
- Produces: CI die op elke PR/push-to-main draait: backend-phpunit (met Postgres-service + migraties), frontend lint/test/build. Taak 2 voegt hier een `contracts`-job aan toe. Plus `make test-db` voor de lokale `app_test`-bootstrap.

**Kritiek detail (uit review):** met `APP_ENV=test` verbinden migrate én phpunit met database **`app_test`** (doctrine.yaml `when@test`: `dbname_suffix: '_test%env(default::TEST_TOKEN)%'`). De Postgres-service maakt alleen `app` aan, dus de workflow moet `app_test` eerst zelf aanmaken. `doctrine:database:create --if-not-exists` respecteert de suffix onder `APP_ENV=test`. Lokaal geldt hetzelfde op een verse worktree-DB (`make seed` migreert alleen `app`).

- [ ] **Step 1: Workflow schrijven**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  backend:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: api
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_USER: app
          POSTGRES_PASSWORD: app
          POSTGRES_DB: app
        ports:
          - 5432:5432
        options: >-
          --health-cmd "pg_isready -U app -d app"
          --health-interval 5s
          --health-timeout 5s
          --health-retries 10
    env:
      APP_ENV: test
      DATABASE_URL: "postgresql://app:app@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"
          extensions: intl, pdo_pgsql, fileinfo
          coverage: none
      - run: composer install --no-interaction --no-progress
      # APP_ENV=test suffixes the dbname to app_test (doctrine.yaml when@test);
      # the postgres service only creates "app", so create app_test first.
      - run: php bin/console doctrine:database:create --if-not-exists
      - run: php bin/console doctrine:migrations:migrate --no-interaction
      - run: php bin/phpunit

  frontend:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: web
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 24
          cache: npm
          cache-dependency-path: web/package-lock.json
      - run: npm ci
      - run: npm run lint
      - run: npm run test
      - run: npm run build
```

- [ ] **Step 2: `make test-db`-target toevoegen** (naast de bestaande targets, en aan `.PHONY`):

```makefile
## Create + migrate the separate test database (app_test) the backend test
## suite runs against (doctrine.yaml when@test dbname_suffix). Fresh
## worktree databases don't have it — `make seed` only migrates `app`.
test-db:
	$(COMPOSE) exec -e APP_ENV=test backend php bin/console doctrine:database:create --if-not-exists
	$(COMPOSE) exec -e APP_ENV=test backend php bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 3: Lokaal valideren**

Run: `make up && make seed && make test-db`, dan `make test-backend` en `make npm args="run test"` en `make npm args="run lint"`
Expected: `test-db` maakt/migreert `app_test`; beide suites groen (nulmeting vóór alles wat volgt).
**Bekend struikelpunt (vendor-drift):** de backend-entrypoint draait `composer install` alleen als `vendor/autoload.php` ontbreekt — na een composer.lock-wijziging dus nooit. Falen KernelTestCases op container-build (bv. ontbrekende Flysystem-classes), draai dan eerst `make composer args="install"` en probeer opnieuw. De workflow-YAML zelf wordt bewezen door de eerste push — verifieer in de PR dat alle jobs groen zijn en fix daar wat CI-specifiek blijkt (bv. een ontbrekende PHP-extensie).

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml Makefile
git commit -m "KOZ-<n>: GitHub Actions CI + make test-db voor de aparte app_test-database"
```

Na de eerste push: verifieer in de PR dat alle jobs echt groen draaien; pas zonodig aan (bv. ontbrekende PHP-extensie) vóór verder te bouwen op deze CI.

---

### Taak 2: Contract-checks (errorKeys + gedeelde constanten)

**Files:**
- Create: `contracts/shared-constants.json` (bron van waarheid, host/CI-niveau)
- Create: `api/config/contract/shared-constants.json` (kopie voor backend-test; identiek gehouden door het checkscript)
- Create: `web/lib/contract/shared-constants.json` (kopie voor frontend-test)
- Create: `scripts/check-contracts.mjs`
- Create: `api/tests/Shared/Contract/SharedConstantsContractTest.php`
- Create: `web/lib/contract/shared-constants.test.ts`
- Modify: `.github/workflows/ci.yml` (extra job), `Makefile` (target `check-contracts`)

**Interfaces:**
- Produces: `node scripts/check-contracts.mjs` (exit 0/1) — checkt (1) dat de drie JSON-kopieën identiek zijn, (2) dat elke backend-errorKey (`<domein>.error.<naam>`) in `web/lib/i18n/resources/nl.json` én `en.json` bestaat.
- Latere taken vertrouwen erop dat een hernoemde errorKey CI laat falen.

- [ ] **Step 1: Failing backend-contracttest schrijven (TDD: test vóór de JSON)**

`api/tests/Shared/Contract/SharedConstantsContractTest.php` (pure unit-test, geen kernel):

```php
<?php

namespace App\Tests\Shared\Contract;

use App\Tenancy\Domain\Subdomain;
use App\Tenancy\Domain\Tenant;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use PHPUnit\Framework\TestCase;

/**
 * Guards the constants the frontend mirrors (see the counterpart test in
 * web/lib/contract/shared-constants.test.ts and scripts/check-contracts.mjs,
 * which keeps the JSON copies identical): whoever changes one of these
 * constants must consciously update the contract file — and thereby the
 * other side — instead of silently drifting apart.
 */
final class SharedConstantsContractTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function contract(): array
    {
        $json = file_get_contents(\dirname(__DIR__, 3) . '/config/contract/shared-constants.json');
        self::assertIsString($json);

        return json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testLocalesMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['locales']['supported'], Tenant::SUPPORTED_LOCALES);
        self::assertSame($contract['locales']['default'], Tenant::DEFAULT_LOCALE);
    }

    public function testTenantUserRolesMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['tenantUserRoles']['admin'], TenantUser::ROLE_TENANT_ADMIN);
        self::assertSame($contract['tenantUserRoles']['default'], TenantUser::DEFAULT_ROLE);
    }

    public function testTokenCookieNameMatchesContract(): void
    {
        self::assertSame(self::contract()['cookies']['tenantApiToken'], TenantApiTokenCookie::NAME);
    }

    public function testReservedSubdomainsMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['reservedSubdomains']['admin'], Subdomain::RESERVED_ADMIN);
        self::assertSame($contract['reservedSubdomains']['api'], Subdomain::RESERVED_API);
    }
}
```

Let op: verifieer eerst het exacte pad/de naam van `TenantApiTokenCookie` en of `NAME` public is (`grep -rn "const NAME" api/src/TenantUser`); pas de import aan op wat er echt staat. Is de constante private, maak hem dan public (geen gedragswijziging).

- [ ] **Step 2: Run — verify FAIL** (`make test-backend args="tests/Shared/Contract"` → FAIL op `file_get_contents`: bestand bestaat nog niet)

- [ ] **Step 3: Contract-JSON schrijven (3× identiek: `contracts/`, `api/config/contract/`, `web/lib/contract/`) en opnieuw draaien → PASS**

```json
{
  "locales": { "supported": ["nl", "en"], "default": "nl" },
  "tenantUserRoles": { "admin": "ROLE_TENANT_ADMIN", "default": "ROLE_TENANT_USER" },
  "cookies": { "tenantApiToken": "tenant_api_token" },
  "reservedSubdomains": { "admin": "admin", "api": "api" }
}
```

- [ ] **Step 4: Frontend-contracttest schrijven**

`web/lib/contract/shared-constants.test.ts`:

```ts
import { describe, expect, it } from "vitest"

import contract from "./shared-constants.json"
import { apiBaseUrl } from "@/lib/api-base-url"
import { DEFAULT_LOCALE, SUPPORTED_LOCALES } from "@/lib/i18n/locale"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"

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
```

Let op: `resolveAppContext` importeert `next/headers` in hetzelfde bestand (`app-context.ts`); als
dat in de node-Vitest-omgeving faalt, verplaats de import-loze `resolveAppContext` niet — mock dan
`next/headers` via `vi.mock("next/headers", () => ({ headers: () => new Map() }))` bovenin de test.

Run: `make npm args="run test"` → PASS.

- [ ] **Step 5: check-contracts-script schrijven**

`scripts/check-contracts.mjs`:

```js
#!/usr/bin/env node
/**
 * Cross-tree contract checks that can't live inside either test suite
 * (the backend/frontend containers each mount only their own tree):
 *
 * 1. The three copies of contracts/shared-constants.json must be identical.
 * 2. Every backend error key (the `<domain>.error.<name>` literals passed to
 *    ValidationException::create / ExceptionResponsePayload::withKey / any
 *    HasErrorKey implementation) must exist in BOTH frontend i18n catalogs —
 *    a renamed key otherwise silently degrades to the English fallback.
 *
 * Known limitation (deliberate): the check is one-way. Frontend-only keys
 * (Zod-form keys like tenants.error.subdomainPattern, frontend fallbacks
 * like tenantSettings.error.saveFailed) are legitimate, so unused catalog
 * keys are NOT flagged. The extraction also only sees full literals: never
 * build an error key by string concatenation in api/src — pass complete
 * literal keys (see StoredImageErrorKeys in Taak 9), or this check silently
 * loses coverage for them.
 *
 * Run from the repo root: `node scripts/check-contracts.mjs` (also wired
 * into CI and `make check-contracts`).
 */
import { readFileSync, readdirSync, statSync } from "node:fs"
import { join } from "node:path"

const root = new URL("..", import.meta.url).pathname
let failed = false
const fail = (msg) => { console.error(`FAIL: ${msg}`); failed = true }

// 1. Identical copies.
const canonical = readFileSync(join(root, "contracts/shared-constants.json"), "utf8")
for (const copy of ["api/config/contract/shared-constants.json", "web/lib/contract/shared-constants.json"]) {
  if (readFileSync(join(root, copy), "utf8") !== canonical) {
    fail(`${copy} differs from contracts/shared-constants.json — copy the canonical file over it.`)
  }
}

// 2. Error keys.
const phpFiles = []
const walk = (dir) => {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) walk(full)
    else if (full.endsWith(".php")) phpFiles.push(full)
  }
}
walk(join(root, "api/src"))

const keyPattern = /['"]([a-zA-Z][a-zA-Z0-9]*\.error\.[a-zA-Z][a-zA-Z0-9]*)['"]/g
const backendKeys = new Set()
for (const file of phpFiles) {
  for (const match of readFileSync(file, "utf8").matchAll(keyPattern)) {
    backendKeys.add(match[1])
  }
}
if (backendKeys.size === 0) fail("no backend error keys found at all — the extraction regex is broken")

const flatten = (obj, prefix = "", out = {}) => {
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key
    if (typeof value === "string") out[path] = value
    else if (value && typeof value === "object") flatten(value, path, out)
  }
  return out
}
const catalogs = Object.fromEntries(
  ["nl", "en"].map((locale) => [
    locale,
    flatten(JSON.parse(readFileSync(join(root, `web/lib/i18n/resources/${locale}.json`), "utf8"))),
  ])
)

for (const key of [...backendKeys].sort()) {
  for (const locale of ["nl", "en"]) {
    if (!(key in catalogs[locale])) fail(`backend error key "${key}" missing from ${locale}.json`)
  }
}

if (failed) process.exit(1)
console.log(`OK: contract copies identical; ${backendKeys.size} backend error keys present in nl+en catalogs.`)
```

- [ ] **Step 6: Script draaien en eventuele échte gaten fixen**

Run: `node scripts/check-contracts.mjs`
Expected: OK — **tenzij** er vandaag al keys ontbreken in een catalogus. Ontbreekt er echt een key
(geen regex-false-positive): voeg de vertaling toe aan `nl.json`/`en.json` (kleine bugfix, in de
geest van dit ticket). Is het een false positive (literal die geen errorKey is): scherp de regex
aan, niet de catalogus.

- [ ] **Step 7: Makefile-target + CI-job toevoegen**

Makefile (naast de bestaande targets, en toevoegen aan `.PHONY`):

```makefile
## Cross-tree contract checks (shared constants + backend errorKeys vs
## frontend i18n catalogs). Runs on the host — needs node, not the stack.
check-contracts:
	node scripts/check-contracts.mjs
```

CI (`.github/workflows/ci.yml`, extra job):

```yaml
  contracts:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 24
      - run: node scripts/check-contracts.mjs
```

- [ ] **Step 8: Alles draaien + commit**

Run: `make test-backend args="tests/Shared/Contract"`, `make npm args="run test"`, `node scripts/check-contracts.mjs`
Expected: alles groen.

```bash
git add contracts api/config/contract api/tests/Shared/Contract web/lib/contract scripts/check-contracts.mjs Makefile .github/workflows/ci.yml
git commit -m "KOZ-<n>: contract-checks voor gedeelde constanten en errorKey-catalogus"
```

---

### Taak 3: Frontend request-kern + opsplitsen `lib/api.ts`

**Files:**
- Create: `web/lib/api/endpoints.test.ts` (karakterisatietests — VÓÓR de refactor, tegen het huidige `@/lib/api`)
- Create: `web/lib/api/http.ts`, `web/lib/api/http.test.ts`
- Create: `web/lib/api/auth.ts`, `web/lib/api/tenants.ts`, `web/lib/api/tenant-users.ts`, `web/lib/api/admin-users.ts`, `web/lib/api/tenant-settings.ts`, `web/lib/api/profile-photo.ts`, `web/lib/api/index.ts`
- Delete: `web/lib/api.ts` (na migratie; `@/lib/api` resolvet dan naar `web/lib/api/index.ts`)

**Interfaces:**
- Consumes: `apiErrorMessage`-logica (verhuist mee), `ActionResult` uit `lib/forms/types`, `translate`/`getClientLocale`, `apiBaseUrl`.
- Produces (voor alle domeinmodules + latere taken):

```ts
// web/lib/api/http.ts
export function backendUrl(path: string): string
export function apiErrorMessage(body: unknown, fallback: string): string

export type ActionRequestOptions = {
  method?: "POST" | "PATCH"
  json?: unknown
  formData?: FormData
  /** Lazily evaluated so a mid-session language switch is reflected. */
  fallbackMessage: () => string
  /** When set, a non-OK response maps to { fieldErrors: { [errorField]: message } } instead of { message }. */
  errorField?: string
}
export function requestAction<T>(path: string, options: ActionRequestOptions): Promise<ActionResult<T>>

/** GET that throws on non-OK (list/detail pages render an error state). */
export function getJsonOrThrow<T>(path: string, what: string): Promise<T>
```

- Alle **publieke exports van het huidige `lib/api.ts` blijven onder `@/lib/api` bestaan met identieke signatures** — geen enkele import elders in `web/` hoeft te wijzigen.

**Gedragscontract van `requestAction` (exact het bestaande patroon):**
1. fetch met `credentials: "include"`; bij `json`: `Content-Type: application/json` + `JSON.stringify`; bij `formData`: geen handmatige Content-Type.
2. Netwerkfout (fetch throwt) → `{ success: false, message: fallbackMessage() }` — óók als `errorField` gezet is (zo doet `createTenantUser` het vandaag).
3. `!response.ok` → body best-effort parsen, `message = apiErrorMessage(body, fallbackMessage())`; met `errorField` → `{ success: false, fieldErrors: { [errorField]: message } }`, anders `{ success: false, message }`.
4. OK → `{ success: true, data: await response.json() }`.

**Wat bewust NIET door de kern gaat** (afwijkende semantiek, blijft bespoke in `auth.ts`/`tenant-settings.ts`/`profile-photo.ts`): `login` (parset `defaultLocale`), `postCredentials`/`adminLogin` (LoginResult zonder body-parse op succes), `postBestEffort`/logouts, `getMe` (null i.p.v. throw), `getProfilePhotoBlob` (blob/null), `fetchTenantLoginImageUrl` (geen credentials, object-URL + ORB-docblock — verhuist ongewijzigd).

- [ ] **Step 1: Karakterisatietests schrijven tegen het HUIDIGE `@/lib/api` — en groen zien**

Er is nu géén test op `lib/api.ts`; deze tabelgedreven suite pint per endpoint-wrapper URL, method, body, credentials en foutmapping vast vóórdat er iets verhuist, en blijft daarna ongewijzigd meedraaien als regressienet. `web/lib/api/endpoints.test.ts` (node-env; `File`/`FormData`/`Response` zijn in Node 24 globals):

```ts
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import * as api from "@/lib/api"

beforeEach(() => {
  vi.stubGlobal("window", { location: { host: "admin.kozijnr.localhost", protocol: "http:" } })
})
afterEach(() => {
  vi.unstubAllGlobals()
})

const jsonResponse = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } })

const file = () => new File(["x"], "x.png", { type: "image/png" })

type ActionCase = {
  name: string
  call: () => Promise<unknown>
  url: string
  method: "POST" | "PATCH"
  json?: unknown
  formDataField?: string
  /** Waar een 422-message landt: een fieldErrors-sleutel, of "message". */
  errorTarget: string
}

// Elke ActionResult-wrapper uit lib/api.ts — deze tabel MOET ze allemaal bevatten.
const actionCases: ActionCase[] = [
  { name: "createTenantUser", call: () => api.createTenantUser("acme", { email: "a@b.nl", role: "ROLE_TENANT_USER" }), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/users", method: "POST", json: { email: "a@b.nl", role: "ROLE_TENANT_USER" }, errorTarget: "email" },
  { name: "createOwnTenantUser", call: () => api.createOwnTenantUser({ email: "a@b.nl", role: "ROLE_TENANT_USER" }), url: "http://api.kozijnr.localhost/api/users", method: "POST", json: { email: "a@b.nl", role: "ROLE_TENANT_USER" }, errorTarget: "email" },
  { name: "createAdminUser", call: () => api.createAdminUser({ email: "a@b.nl" }), url: "http://api.kozijnr.localhost/api/admin/users", method: "POST", json: { email: "a@b.nl" }, errorTarget: "email" },
  { name: "createTenant", call: () => api.createTenant({ name: "Acme", slug: "acme", adminEmail: "a@b.nl" }), url: "http://api.kozijnr.localhost/api/admin/tenants", method: "POST", json: { name: "Acme", slug: "acme", adminEmail: "a@b.nl" }, errorTarget: "slug" },
  { name: "updateTenant", call: () => api.updateTenant("acme", { name: "Acme 2", slug: "acme-2" }), url: "http://api.kozijnr.localhost/api/admin/tenants/acme", method: "PATCH", json: { name: "Acme 2", slug: "acme-2" }, errorTarget: "slug" },
  { name: "archiveTenant", call: () => api.archiveTenant("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/archive", method: "POST", errorTarget: "message" },
  { name: "unarchiveTenant", call: () => api.unarchiveTenant("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/unarchive", method: "POST", errorTarget: "message" },
  { name: "updateTenantDefaultLocale", call: () => api.updateTenantDefaultLocale({ defaultLocale: "en" }), url: "http://api.kozijnr.localhost/api/settings/locale", method: "PATCH", json: { defaultLocale: "en" }, errorTarget: "defaultLocale" },
  { name: "uploadTenantLoginImage", call: () => api.uploadTenantLoginImage(file()), url: "http://api.kozijnr.localhost/api/settings/login-image", method: "POST", formDataField: "image", errorTarget: "loginImage" },
  { name: "uploadProfilePhoto", call: () => api.uploadProfilePhoto(file()), url: "http://api.kozijnr.localhost/api/me/profile-photo", method: "POST", formDataField: "photo", errorTarget: "message" },
]

describe.each(actionCases)("$name", ({ call, url, method, json, formDataField, errorTarget }) => {
  it("hits the right endpoint with credentials and returns the parsed data", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { ok: true }))
    vi.stubGlobal("fetch", fetchMock)

    const result = await call()

    const [calledUrl, init] = fetchMock.mock.calls[0]
    expect(calledUrl).toBe(url)
    expect(init).toMatchObject({ method, credentials: "include" })
    if (json !== undefined) {
      expect(init.headers).toMatchObject({ "Content-Type": "application/json" })
      expect(JSON.parse(init.body as string)).toEqual(json)
    }
    if (formDataField) {
      expect(init.body).toBeInstanceOf(FormData)
      expect((init.body as FormData).get(formDataField)).toBeInstanceOf(File)
    }
    expect(result).toEqual({ success: true, data: { ok: true } })
  })

  it("maps a non-OK response onto the right target", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(422, { message: "Nope" })))

    const result = (await call()) as { success: false; message?: string; fieldErrors?: Record<string, string> }

    expect(result.success).toBe(false)
    if (errorTarget === "message") expect(result.message).toBe("Nope")
    else expect(result.fieldErrors).toEqual({ [errorTarget]: "Nope" })
  })

  it("returns a plain message on a network error", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))

    const result = (await call()) as { success: false; message?: string; fieldErrors?: unknown }

    expect(result.success).toBe(false)
    expect(typeof result.message).toBe("string")
    expect(result.fieldErrors).toBeUndefined()
  })
})

type GetCase = { name: string; call: () => Promise<unknown>; url: string }

// Elke throw-on-!ok GET-wrapper — ook deze tabel MOET volledig zijn.
const getCases: GetCase[] = [
  { name: "listTenants", call: () => api.listTenants(), url: "http://api.kozijnr.localhost/api/admin/tenants" },
  { name: "listTenants archived", call: () => api.listTenants(true), url: "http://api.kozijnr.localhost/api/admin/tenants?archived=true" },
  { name: "listTenantUsers", call: () => api.listTenantUsers("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/users" },
  { name: "listAdminUsers", call: () => api.listAdminUsers(), url: "http://api.kozijnr.localhost/api/admin/users" },
  { name: "listOwnTenantUsers", call: () => api.listOwnTenantUsers(), url: "http://api.kozijnr.localhost/api/users" },
  { name: "getTenantSettings", call: () => api.getTenantSettings(), url: "http://api.kozijnr.localhost/api/settings" },
]

describe.each(getCases)("$name", ({ call, url }) => {
  it("GETs with credentials and returns the parsed body", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, [{ id: 1 }]))
    vi.stubGlobal("fetch", fetchMock)

    await expect(call()).resolves.toEqual([{ id: 1 }])
    expect(fetchMock).toHaveBeenCalledWith(url, { method: "GET", credentials: "include" })
  })

  it("throws on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 500 })))
    await expect(call()).rejects.toThrow(/status 500/)
  })
})

describe("bespoke endpoints", () => {
  it("login returns the tenant's default locale on success", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(200, { defaultLocale: "en" })))
    await expect(api.login({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true, data: { defaultLocale: "en" } })
  })

  it("login falls back to the default locale on an unsupported value", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(200, { defaultLocale: "xx" })))
    await expect(api.login({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true, data: { defaultLocale: "nl" } })
  })

  it("adminLogin succeeds without parsing a body", async () => {
    // Let op: new Response("", { status: 204 }) gooit een TypeError in Node
    // (null-body-status accepteert geen body) — daarom null.
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 204 })))
    await expect(api.adminLogin({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true })
  })

  it("getMe returns null instead of throwing on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 401 })))
    await expect(api.getMe()).resolves.toBeNull()
  })

  it("getProfilePhotoBlob returns null on 404 and a Blob on success", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 404 })))
    await expect(api.getProfilePhotoBlob()).resolves.toBeNull()

    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(new Blob(["img"]), { status: 200 })))
    await expect(api.getProfilePhotoBlob()).resolves.toBeInstanceOf(Blob)
  })

  it("fetchTenantLoginImageUrl fetches WITHOUT credentials and returns an object URL", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(new Blob(["img"]), { status: 200 }))
    vi.stubGlobal("fetch", fetchMock)
    // spyOn i.p.v. stubGlobal met object-spread: spreading van de URL-class
    // kopieert geen statics, en de spy herstelt zichzelf netjes.
    vi.spyOn(URL, "createObjectURL").mockReturnValue("blob:x")

    await expect(api.fetchTenantLoginImageUrl()).resolves.toBe("blob:x")
    // Tweede fetch-argument is undefined: géén credentials — het Origin/ORB-
    // gedrag (zie het docblock in de bron) hangt aan een kale fetch().
    expect(fetchMock.mock.calls[0][1]).toBeUndefined()
  })

  it("logout and adminLogout swallow network errors (best effort)", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))
    await expect(api.logout()).resolves.toBeUndefined()
    await expect(api.adminLogout()).resolves.toBeUndefined()
  })
})
```

Run: `make npm args="run test -- lib/api/endpoints.test.ts"`
Expected: **PASS tegen het huidige `lib/api.ts`** — faalt er iets, dan is de tabel fout (fix de
test, niet de code). Deze suite blijft daarna letterlijk ongewijzigd staan; elke stap hierna moet
hem groen houden. Commit deze stap apart:

```bash
git add web/lib/api/endpoints.test.ts
git commit -m "KOZ-<n>: karakterisatietests voor alle lib/api-endpoint-wrappers"
```

- [ ] **Step 2: Failing tests voor de kern schrijven**

`web/lib/api/http.test.ts` (node-env; `window` stubben):

```ts
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import { apiErrorMessage, backendUrl, getJsonOrThrow, requestAction } from "./http"

beforeEach(() => {
  vi.stubGlobal("window", { location: { host: "admin.kozijnr.localhost", protocol: "http:" } })
})
afterEach(() => {
  vi.unstubAllGlobals()
})

const jsonResponse = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } })

describe("backendUrl", () => {
  it("targets api.<base> derived from the current host", () => {
    expect(backendUrl("/api/me")).toBe("http://api.kozijnr.localhost/api/me")
  })
})

describe("apiErrorMessage", () => {
  it("translates a known errorKey", () => {
    // tenants.error.createFailed exists in both catalogs (used by lib/api today).
    expect(apiErrorMessage({ errorKey: "tenants.error.createFailed" }, "fallback")).not.toBe("fallback")
  })
  it("falls back to the body message for an unknown key", () => {
    expect(apiErrorMessage({ errorKey: "nope.error.unknown", message: "Boom" }, "fallback")).toBe("Boom")
  })
  it("falls back to the caller fallback without a body", () => {
    expect(apiErrorMessage(null, "fallback")).toBe("fallback")
  })
})

describe("requestAction", () => {
  it("returns success with parsed data", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(201, { email: "a@b.nl" })))
    const result = await requestAction<{ email: string }>("/api/x", { method: "POST", json: { a: 1 }, fallbackMessage: () => "failed" })
    expect(result).toEqual({ success: true, data: { email: "a@b.nl" } })
    expect(vi.mocked(fetch).mock.calls[0][1]).toMatchObject({ credentials: "include", method: "POST" })
  })

  it("maps a non-OK response onto the configured errorField", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(422, { message: "Bad email" })))
    const result = await requestAction("/api/x", { method: "POST", json: {}, fallbackMessage: () => "failed", errorField: "email" })
    expect(result).toEqual({ success: false, fieldErrors: { email: "Bad email" } })
  })

  it("returns a plain message on a network error, even with errorField set", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))
    const result = await requestAction("/api/x", { method: "POST", json: {}, fallbackMessage: () => "failed", errorField: "email" })
    expect(result).toEqual({ success: false, message: "failed" })
  })
})

describe("getJsonOrThrow", () => {
  it("throws with the status on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 500 })))
    await expect(getJsonOrThrow("/api/x", "tenants")).rejects.toThrow("Failed to load tenants (status 500).")
  })
})
```

- [ ] **Step 3: Run tests — verify FAIL** (`make npm args="run test -- lib/api/http.test.ts"` → module bestaat niet)

- [ ] **Step 4: `web/lib/api/http.ts` implementeren**

Verplaats `apiErrorMessage`, `isRecord` en `backendUrl` letterlijk uit het huidige `api.ts` (incl. het KOZ-29-docblock bovenaan) en voeg toe:

```ts
export async function requestAction<T>(path: string, options: ActionRequestOptions): Promise<ActionResult<T>> {
  const { method = "POST", json, formData, fallbackMessage, errorField } = options

  let response: Response
  try {
    response = await fetch(backendUrl(path), {
      method,
      credentials: "include",
      ...(json !== undefined
        ? { headers: { "Content-Type": "application/json" }, body: JSON.stringify(json) }
        : {}),
      ...(formData !== undefined ? { body: formData } : {}),
    })
  } catch {
    return { success: false, message: fallbackMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, fallbackMessage())
    return errorField ? { success: false, fieldErrors: { [errorField]: message } } : { success: false, message }
  }

  return { success: true, data: await response.json() }
}

export async function getJsonOrThrow<T>(path: string, what: string): Promise<T> {
  const response = await fetch(backendUrl(path), { method: "GET", credentials: "include" })

  if (!response.ok) {
    throw new Error(`Failed to load ${what} (status ${response.status}).`)
  }

  return response.json()
}
```

- [ ] **Step 5: Run tests — verify PASS**

- [ ] **Step 6: Domeinmodules aanmaken, functie voor functie verhuizen**

Verdeling (types verhuizen mee met hun functies; docblocks behouden):
- `auth.ts`: `LoginResult`, `TenantLoginResult`, `CurrentTenantUser`, `login`, `adminLogin`, `logout`, `adminLogout`, `getMe`, plus privé `postCredentials`, `postBestEffort` (ongewijzigd, op `backendUrl`-import na).
- `tenants.ts`: `TenantSummary`, `TenantAdminCredentials`, `CreatedTenant`, `TenantPayload`, `CreateTenantPayload`, `listTenants` → `getJsonOrThrow("/api/admin/tenants" + query, "tenants")`, `getTenant`, `createTenant`/`updateTenant` → `requestAction(..., { errorField: "slug" })` (vervangt `submitTenantPayload`), `archiveTenant`/`unarchiveTenant` → `requestAction` zonder `errorField` (vervangt `postTenantAction`), + de lazy `tenant*FailedMessage()`-helpers. **Bewuste micro-afwijking:** deze vier evalueren hun fallback-message vandaag eager bij aanroep (als argument aan de helper); via `requestAction` wordt dat lazy-bij-falen, precies zoals `createTenantUser` het al doet. Effect: alleen een taalswitch tijdens een in-flight request pakt de nieuwe taal — gewenst, en consistent met de rest.
- `tenant-users.ts`: `TenantUserSummary`, `CreateTenantUserPayload`, `CreatedTenantUser`, `listTenantUsers`, `listOwnTenantUsers` (beide `getJsonOrThrow`), `createTenantUser`/`createOwnTenantUser` → `requestAction(..., { errorField: "email" })`.
- `admin-users.ts`: `AdminUserSummary`, `AdminUserCredentials`, `CreatedAdminUser`, `CreateAdminUserPayload`, `listAdminUsers`, `createAdminUser` → `requestAction(..., { errorField: "email" })`.
- `tenant-settings.ts`: `TenantSettings`, `getTenantSettings` (`getJsonOrThrow`), `updateTenantDefaultLocale` → `requestAction(..., { method: "PATCH", errorField: "defaultLocale" })`, `uploadTenantLoginImage` → `requestAction(..., { formData, errorField: "loginImage" })`, `fetchTenantLoginImageUrl` (bespoke, ongewijzigd incl. ORB-docblock).
- `profile-photo.ts`: `ProfilePhotoMeta`, `uploadProfilePhoto` → `requestAction(..., { formData })` (let op: mapt naar `message`, géén errorField — zo is het vandaag), `getProfilePhotoBlob` (bespoke).
- `index.ts`: `export * from "./auth"` etc. voor alle domeinmodules + `export { apiErrorMessage } from "./http"` alleen als iets buiten `lib/api` het importeert (check: `grep -rn "apiErrorMessage" web --include="*.ts*" | grep -v lib/api`).

Voorbeeld van het patroon (createTenantUser, ter illustratie van de dunne wrapper):

```ts
export async function createTenantUser(
  subdomain: string,
  payload: CreateTenantUserPayload
): Promise<ActionResult<CreatedTenantUser>> {
  return requestAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/users`, {
    json: payload,
    fallbackMessage: tenantUserCreateFailedMessage,
    errorField: "email",
  })
}
```

- [ ] **Step 7: `web/lib/api.ts` verwijderen en resolutie verifiëren**

Run: `rm web/lib/api.ts && make npm args="run test"` en `make npm args="run lint"` en `make npm args="run build"`
Expected: alles groen; `@/lib/api` resolvet naar de nieuwe directory-index. Faalt de build op de
module-resolutie, herstel dan niet met een tweede naam — fix het pad (tsconfig-paths dekken
`@/*` → `./*`, directory-index werkt standaard).

- [ ] **Step 8: Handmatige smoke-test in de browser**

Stack draaien (`make up && make seed`). Admin-realm (`admin.koz-<n>.kozijnr.localhost`, `admin@kozijnr.nl`): login, tenants-lijst, tenant **aanmaken, bewerken (PATCH!), archiveren én unarchiveren**, gebruikers-tab van een tenant, **admin-user aanmaken**, logout. Tenant-realm (`tenant1.koz-<n>.kozijnr.localhost`, `tenant@kozijnr.nl`): login (incl. tenant-default-locale-switch), gebruikerslijst + gebruiker toevoegen, settings (locale wijzigen + **login-image uploaden en op het loginscherm terugzien** — het Origin/ORB-pad), profielfoto uploaden/tonen, logout. Foutpaden: per veld-mapping minstens één fout uitlokken (duplicate e-mail → veldfout op e-mail; bestaande subdomain → veldfout op slug; te groot/verkeerd bestandstype bij beide uploads) en dat in **beide talen** controleren.

- [ ] **Step 9: Commit**

```bash
git add web/lib/api web/lib/api.ts
git commit -m "KOZ-<n>: lib/api.ts opgesplitst rond één requestAction/getJsonOrThrow-kern"
```

---

### Taak 4: Eén CredentialsDialog

**Files:**
- Create: `web/components/credentials-dialog.tsx`
- Modify: `web/app/(app)/tenants/page.tsx`, `web/app/(app)/users/admin-users-page.tsx`, `web/app/(app)/users/own-users-page.tsx`, `web/components/tenants/tenant-users-tab.tsx`
- Delete: `web/components/admin-users/admin-user-credentials-dialog.tsx`, `web/components/tenants/tenant-admin-credentials-dialog.tsx`, `web/components/tenants/tenant-user-credentials-dialog.tsx`

**Interfaces:**
- Produces:

```tsx
export type Credentials = { email: string; password: string; roles?: string[] }
export function CredentialsDialog(props: {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: Credentials | null
  /** i18n-keyprefix: `${prefix}Title|Description|EmailLabel|PasswordLabel` en, bij roles, `${prefix}RoleLabel`. */
  i18nPrefix: "users.adminCredentials" | "tenants.adminCredentials" | "users.credentials"
}): React.JSX.Element | null
```

- Geen catalogus-wijzigingen: de bestaande keys per context blijven in gebruik.

- [ ] **Step 1: Component schrijven** (structuur = de huidige `tenant-user-credentials-dialog.tsx`, met de rollen-rij achter `credentials.roles !== undefined`, en `roleLabel(...)` voor de rolweergave; het samenvattende docblock van de drie oude componenten samenvoegen tot één "shown once"-uitleg).

```tsx
"use client"

import { useTranslation } from "react-i18next"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { roleLabel } from "@/lib/i18n/role-labels"

export type Credentials = { email: string; password: string; roles?: string[] }

interface CredentialsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: Credentials | null
  i18nPrefix: "users.adminCredentials" | "tenants.adminCredentials" | "users.credentials"
}

/**
 * One-time credentials dialog: shown right after an account with a
 * generated password is created (tenant admin on tenant create, KOZ-27;
 * tenant user, KOZ-31; admin user, KOZ-30). The password only ever exists
 * in that one response (only its hash is persisted server-side), so this
 * is the single chance to hand it over. The i18nPrefix keeps each flow's
 * existing catalog keys; a roles row renders only when the caller has
 * roles to show.
 */
export function CredentialsDialog({ open, onOpenChange, credentials, i18nPrefix }: CredentialsDialogProps) {
  const { t } = useTranslation()

  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t(`${i18nPrefix}Title`)}</DialogTitle>
          <DialogDescription>{t(`${i18nPrefix}Description`)}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">{t(`${i18nPrefix}EmailLabel`)}</dt>
          <dd>{credentials.email}</dd>
          {credentials.roles !== undefined && (
            <>
              <dt className="text-muted-foreground">{t(`${i18nPrefix}RoleLabel`)}</dt>
              <dd>{credentials.roles.map((role) => roleLabel(role, t)).join(", ")}</dd>
            </>
          )}
          <dt className="text-muted-foreground">{t(`${i18nPrefix}PasswordLabel`)}</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>{t("common.close")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
```

- [ ] **Step 2: De vier call-sites omzetten**

- `admin-users-page.tsx`: `<CredentialsDialog i18nPrefix="users.adminCredentials" credentials={createdUser} ...>` — `AdminUserCredentials` heeft geen `roles`-veld dus de rollen-rij blijft vanzelf weg.
- `tenants/page.tsx`: prefix `"tenants.adminCredentials"`, credentials = het bestaande `tenantAdmin`-object.
- `own-users-page.tsx` + `tenant-users-tab.tsx`: prefix `"users.credentials"`, credentials = `CreatedTenantUser` (heeft `roles` → rij toont).

Let op in `tenants/page.tsx`: check het huidige gebruik (`grep -n "TenantAdminCredentialsDialog" "web/app/(app)/tenants/page.tsx"`) en behoud de bestaande open/close-state exact.

- [ ] **Step 3: Oude componenten verwijderen** en verifiëren dat niets ze nog importeert: `grep -rn "credentials-dialog" web --include="*.tsx" | grep -v components/credentials-dialog`.

- [ ] **Step 4: Verifiëren + commit**

Run: `make npm args="run test"`, `make npm args="run lint"`, `make npm args="run build"`; smoke-test: maak in de browser een tenant, een tenant-user en een admin-user aan en controleer alle drie de dialogen (incl. NL/EN-switch).

```bash
git add web/components web/app
git commit -m "KOZ-<n>: drie credentials-dialogen samengevoegd tot één CredentialsDialog"
```

---

### Taak 5: `useLoadState`-hook + gedeelde users-tabel

**Files:**
- Create: `web/lib/hooks/use-load-state.ts`, `web/lib/hooks/use-load-state.test.ts`
- Create: `web/components/users/users-table.tsx`
- Modify: `web/app/(app)/users/own-users-page.tsx`, `web/components/tenants/tenant-users-tab.tsx`, `web/package.json` (devDeps), `web/vitest.config.mts` (indien nodig voor jsdom-per-file)
- Delete: niets (admin-users-page/AdminUsersTable blijven zoals ze zijn — ander patroon: refresh-by-remount + DataTable)

**Interfaces:**
- Produces:

```ts
export type LoadState<T> =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; data: T }

/**
 * Het gedeelde useEffect+cancelled-patroon van de lijstpagina's. Geeft de
 * raw setter terug zodat call-sites hun bestaande prepend/upgrade-gedrag
 * (handleCreated) exact kunnen behouden.
 */
export function useLoadState<T>(
  load: () => Promise<T>,
  deps: React.DependencyList
): [LoadState<T>, React.Dispatch<React.SetStateAction<LoadState<T>>>]
```

```tsx
/** Tabel-markup (email + rollen) die own-users-page en tenant-users-tab nu dupliceren. */
export function UsersTable({ users }: { users: TenantUserSummary[] }): React.JSX.Element
```

- [ ] **Step 1: devDeps installeren**

Run: `make npm args="install --save-dev @testing-library/react @testing-library/dom jsdom"`
(`@testing-library/dom` is een peer van @testing-library/react 16 — expliciet meenemen. Controleer daarna dat de `react`/`react-dom`-peer-versies matchen — React 19.)

- [ ] **Step 2: Failing hooktest schrijven**

`web/lib/hooks/use-load-state.test.ts` (jsdom via file-pragma zodat de rest van de suite in node blijft):

```ts
// @vitest-environment jsdom
import { describe, expect, it } from "vitest"
import { act, renderHook, waitFor } from "@testing-library/react"

import { useLoadState } from "./use-load-state"

describe("useLoadState", () => {
  it("goes loading -> loaded with the fetched data", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.resolve(["a"]), []))
    expect(result.current[0]).toEqual({ status: "loading" })
    await waitFor(() => expect(result.current[0]).toEqual({ status: "loaded", data: ["a"] }))
  })

  it("goes to error when the loader rejects", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.reject(new Error("x")), []))
    await waitFor(() => expect(result.current[0]).toEqual({ status: "error" }))
  })

  it("ignores a stale resolution that lands after the deps changed", async () => {
    // De race die de cancelled-guard echt afdekt: de eerste loader resolvet
    // pas NADAT de tweede al geladen is — zonder guard overschrijft het
    // verouderde resultaat de verse state en faalt deze test aantoonbaar.
    const resolvers: Array<(v: string[]) => void> = []
    const load = () => new Promise<string[]>((resolve) => resolvers.push(resolve))
    const { result, rerender } = renderHook(({ dep }) => useLoadState(load, [dep]), {
      initialProps: { dep: 1 },
    })

    rerender({ dep: 2 })
    await act(async () => {
      resolvers[1](["fresh"])
      await Promise.resolve()
    })
    await waitFor(() => expect(result.current[0]).toEqual({ status: "loaded", data: ["fresh"] }))

    await act(async () => {
      resolvers[0](["stale"])
      await Promise.resolve()
    })
    expect(result.current[0]).toEqual({ status: "loaded", data: ["fresh"] })
  })

  it("exposes the raw setter for optimistic updates", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.resolve(["a"]), []))
    await waitFor(() => expect(result.current[0].status).toBe("loaded"))
    act(() => {
      result.current[1]((current) =>
        current.status === "loaded" ? { status: "loaded", data: ["b", ...current.data] } : current
      )
    })
    expect(result.current[0]).toEqual({ status: "loaded", data: ["b", "a"] })
  })
})
```

- [ ] **Step 3: Run — verify FAIL**, dan implementeren:

```ts
import * as React from "react"

export type LoadState<T> =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; data: T }

/**
 * Shared fetch-on-mount pattern for the list pages: loading -> loaded/error,
 * with the usual cancelled-flag guard so a response landing after unmount
 * (or after the deps changed) never writes stale state. Returns the raw
 * setter so call sites keep their existing optimistic updates (e.g.
 * prepending a freshly created user) verbatim.
 */
export function useLoadState<T>(
  load: () => Promise<T>,
  deps: React.DependencyList
): [LoadState<T>, React.Dispatch<React.SetStateAction<LoadState<T>>>] {
  const [state, setState] = React.useState<LoadState<T>>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false
    setState({ status: "loading" })

    load()
      .then((data) => {
        if (!cancelled) setState({ status: "loaded", data })
      })
      .catch(() => {
        if (!cancelled) setState({ status: "error" })
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- caller-supplied deps, same contract as useEffect itself
  }, deps)

  return [state, setState]
}
```

Run: `make npm args="run test -- lib/hooks"` → PASS.

- [ ] **Step 4: `UsersTable` schrijven** (letterlijk de nu dubbele markup):

```tsx
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
```

- [ ] **Step 5: `own-users-page.tsx` en `tenant-users-tab.tsx` omzetten**

- `tenant-users-tab.tsx`: `useLoadState(() => listTenantUsers(subdomain), [subdomain])`; `handleCreated` gebruikt de setter met hetzelfde prepend-gedrag; tabel-markup → `<UsersTable users={state.data} />`. Loading/error/empty-rendering blijft per component (de twee pagina's renderen die verschillend — geen kunstmatige unificatie). **Laat de `key={subdomain}`-wrapper op de detailpagina staan**: dat remount-mechanisme en de deps van de hook zijn bewust dubbel (belt & braces); de wrapper "wegfixen" is buiten scope.
- `own-users-page.tsx`: `useLoadState(() => Promise.all([listOwnTenantUsers(), getMe()]).then(([users, me]) => ({ users, isTenantAdmin: me?.roles.includes(TenantUser.ROLE_TENANT_ADMIN) ?? false })), [])` — de state-shape `{ users, isTenantAdmin }` blijft; `handleCreated` idem via de setter.
- De lokale `LoadState`-unions in beide bestanden verwijderen.

- [ ] **Step 6: Verifiëren + commit**

Run: `make npm args="run test"`, `make npm args="run lint"`, `make npm args="run build"`; smoke-test beide lijsten + "Gebruiker toevoegen"-flow.

```bash
git add web/lib/hooks web/components/users web/app web/components/tenants web/package.json web/package-lock.json
git commit -m "KOZ-<n>: useLoadState-hook en gedeelde UsersTable voor de tenant-userlijsten"
```

---

### Taak 6: Eén gedeelde PasswordHasher-poort (backend)

**Files:**
- Create: `api/src/Shared/Domain/Security/PasswordHasherInterface.php`
- Create: `api/src/Shared/Infrastructure/Security/SymfonyPasswordHasher.php`
- Create: `api/tests/Shared/Infrastructure/Security/SymfonyPasswordHasherTest.php`
- Modify: `api/config/services.yaml`, `api/src/User/Application/CreateSuperAdmin.php`, `api/src/TenantUser/Application/CreateTenantUser.php`, `api/src/TenantUser/Application/LoginTenantUser.php`, en de tests die de oude poorten mocken (`CreateSuperAdminTest`, `CreateTenantUserTest`, `LoginTenantUserTest`, e.a. — vind ze met `grep -rln "PasswordHasherInterface" api/tests`)
- Delete: `api/src/User/Domain/PasswordHasherInterface.php`, `api/src/TenantUser/Domain/PasswordHasherInterface.php`, `api/src/User/Infrastructure/Security/SymfonyPasswordHasher.php`, `api/src/TenantUser/Infrastructure/Security/SymfonyPasswordHasher.php`, `api/tests/User/Infrastructure/Security/SymfonyPasswordHasherTest.php` (+ eventuele TenantUser-tegenhanger)

**Interfaces:**
- Produces:

```php
namespace App\Shared\Domain\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Shared abstraction for hashing/verifying passwords, so Application code
 * never depends on Symfony's hasher directly. One port for both realms:
 * the previous per-context ports (User\Domain, TenantUser\Domain) were
 * byte-identical apart from the user type, and Symfony's own
 * UserPasswordHasherInterface is already generic over
 * PasswordAuthenticatedUserInterface — which both User and TenantUser
 * implement. Domain already depends on that Symfony interface via the
 * entities themselves, so this introduces no new layering direction.
 */
interface PasswordHasherInterface
{
    public function hash(PasswordAuthenticatedUserInterface $user, string $plainPassword): string;

    public function verify(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool;
}
```

Adapter (`App\Shared\Infrastructure\Security\SymfonyPasswordHasher`): zelfde shape als de huidige TenantUser-variant, maar getypeerd op `PasswordAuthenticatedUserInterface`, met `hashPassword`/`isPasswordValid`.

- [ ] **Step 1: Failing adapter-test schrijven** — kopieer de bestaande `api/tests/User/Infrastructure/Security/SymfonyPasswordHasherTest.php` naar `api/tests/Shared/Infrastructure/Security/`, hernoem namespace/imports naar de Shared-klassen en dek zowel `hash()` als `verify()` (round-trip met een echte `User` én een echte `TenantUser`-instantie, zodat de generiekheid bewezen is).

- [ ] **Step 2: Run — FAIL** (`make test-backend args="tests/Shared/Infrastructure"`), dan poort + adapter implementeren, dan PASS.

- [ ] **Step 3: Call-sites omzetten (één commit)**

- `CreateSuperAdmin`/`CreateTenantUser`/`LoginTenantUser`: import wijzigen naar `App\Shared\Domain\Security\PasswordHasherInterface` — de aanroepen (`hash($user, $plain)`, `verify($user, $plain)`) blijven identiek.
- `services.yaml`: de twee oude aliassen vervangen door:

```yaml
    App\Shared\Domain\Security\PasswordHasherInterface: '@App\Shared\Infrastructure\Security\SymfonyPasswordHasher'
```

- Testmocks: overal `createMock(\App\...\PasswordHasherInterface::class)` → de Shared-poort.
- Oude vier klassen + oude adapter-test(s) verwijderen; `grep -rn "User\\\\Domain\\\\PasswordHasherInterface\|TenantUser\\\\Domain\\\\PasswordHasherInterface" api` moet leeg zijn.

- [ ] **Step 4: Volledige backend-suite draaien + commit**

Run: `make test-backend`
Expected: PASS (login-flows zitten in de API-tests — die bewijzen dat de container-binding klopt).

```bash
git add api
git commit -m "KOZ-<n>: één gedeelde PasswordHasher-poort in Shared i.p.v. twee identieke per context"
```

---

### Taak 7: `GeneratedPassword` + e-mailvalidatiehelper (backend)

**Files:**
- Create: `api/src/Shared/Domain/Security/GeneratedPassword.php`, `api/tests/Shared/Domain/Security/GeneratedPasswordTest.php`
- Create: `api/src/Shared/Domain/EmailAddress.php`, `api/tests/Shared/Domain/EmailAddressTest.php`
- Modify: `api/src/User/Application/CreateAdminUser.php`, `api/src/TenantUser/Application/CreateTenantUserForCurrentTenant.php`, `api/src/Tenancy/Application/ProvisionTenantWithAdmin.php`

**Interfaces:**
- Produces:

```php
namespace App\Shared\Domain\Security;

/**
 * The one-time generated password handed out exactly once in a create
 * response (KOZ-27/30/31 pattern). One definition of its shape/entropy
 * instead of three inlined bin2hex(random_bytes(12)) call sites.
 */
final class GeneratedPassword
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(12));
    }
}
```

```php
namespace App\Shared\Domain;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The trim + FILTER_VALIDATE_EMAIL check the three account-creating use
 * cases each inlined. The English message and errorKey stay caller-supplied
 * because each context reports its own key (users.error.emailInvalid /
 * tenants.error.userEmailInvalid / tenants.error.adminEmailInvalid) — the
 * check is shared, the contract per endpoint is not.
 */
final class EmailAddress
{
    public static function validated(string $raw, string $englishMessage, string $errorKey): string
    {
        $email = trim($raw);

        if ($email === '' || filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::create($englishMessage, $errorKey);
        }

        return $email;
    }
}
```

- Placeholder-hash-patroon (a2) blijft bewust staan: 3 regels per call-site, load-bearing voor Symfony's hasher-API — abstraheren voegt indirectie toe zonder echte winst.

- [ ] **Step 1: Failing tests schrijven**

```php
// GeneratedPasswordTest
public function testGeneratesTwentyFourLowercaseHexCharacters(): void
{
    $password = GeneratedPassword::generate();
    self::assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $password);
}

public function testGeneratesADifferentPasswordEachTime(): void
{
    self::assertNotSame(GeneratedPassword::generate(), GeneratedPassword::generate());
}
```

```php
// EmailAddressTest
public function testTrimsAndReturnsAValidEmail(): void
{
    self::assertSame('a@b.nl', EmailAddress::validated('  a@b.nl ', 'Invalid.', 'x.error.emailInvalid'));
}

public function testThrowsWithTheCallersMessageAndKeyForAnInvalidEmail(): void
{
    try {
        EmailAddress::validated('nope', 'Admin user email must be a valid email address.', 'users.error.emailInvalid');
        self::fail('Expected ValidationException');
    } catch (ValidationException $exception) {
        self::assertSame('Admin user email must be a valid email address.', $exception->getMessage());
        self::assertSame('users.error.emailInvalid', $exception->getErrorKey());
    }
}

public function testThrowsForAnEmptyOrWhitespaceEmail(): void
{
    $this->expectException(ValidationException::class);
    EmailAddress::validated('   ', 'Invalid.', 'x.error.emailInvalid');
}
```

- [ ] **Step 2: Run FAIL → implementeren → PASS** (`make test-backend args="tests/Shared/Domain"`)

- [ ] **Step 3: De drie call-sites omzetten** — bijv. in `CreateAdminUser`:

```php
$trimmedEmail = EmailAddress::validated(
    $email,
    'Admin user email must be a valid email address.',
    'users.error.emailInvalid',
);

$password = GeneratedPassword::generate();
```

Zelfde substitutie in `CreateTenantUserForCurrentTenant` (key `tenants.error.userEmailInvalid`) en `ProvisionTenantWithAdmin` (key `tenants.error.adminEmailInvalid`). Bestaande Engelse messages letterlijk behouden. De bestaande use-case-tests (o.a. `CreateAdminUserTest`, `CreateTenantUserForCurrentTenantTest`) bewaken dat messages/keys niet wijzigen.

- [ ] **Step 4: Volledige backend-suite + `node scripts/check-contracts.mjs` + commit**

```bash
git add api
git commit -m "KOZ-<n>: GeneratedPassword en EmailAddress::validated i.p.v. drie inline kopieën"
```

---

### Taak 8: TenantSchemaContext — één plek voor de search_path-switch

**Files:**
- Create: `api/src/Tenancy/Domain/TenantSchemaContextInterface.php`
- Create: `api/src/Tenancy/Infrastructure/DbalTenantSchemaContext.php`
- Create: `api/tests/Tenancy/Infrastructure/DbalTenantSchemaContextTest.php` (integratie, KernelTestCase, echte connectie)
- Create: `api/tests/TenantUser/Infrastructure/Command/CreateTenantUserCommandTest.php` (**vóór de migratie van dat command** — het heeft nu nul dekking)
- Modify: `api/config/services.yaml`; call-sites in volgorde: `api/src/Tenancy/Application/ProvisionTenantWithAdmin.php`, `api/src/TenantUser/Application/ListTenantUsers.php`, `api/src/TenantUser/Application/CreateTenantUserForTenant.php`, `api/src/TenantUser/Infrastructure/Command/CreateTenantUserCommand.php`, `api/src/Shared/Infrastructure/Command/SeedDevFixturesCommand.php`, en de reset-only-sites `ProvisionTenant.php`, `ArchiveTenant.php`, `UnarchiveTenant.php`, `UpdateTenant.php`
- Modify (verplicht, deel van de review): `api/tests/Tenancy/Application/ArchiveTenantTest.php`, `UnarchiveTenantTest.php`, `UpdateTenantTest.php` — deze mocken nu de `Connection` en asserten `executeStatement('SET search_path TO public')` exact éénmaal; na de constructor-swap compileren ze niet meer. Voorgeschreven nieuwe verwachting: mock `TenantSchemaContextInterface`, assert `resetToPublic()` exact éénmaal, aangeroepen vóór de repository-lookup (gebruik dezelfde mock-stijl als de huidige tests). Dit is een mechanische, gereviewde rewrite — geen gelegenheid om de asserts te verzwakken.

**Interfaces:**
- Produces:

```php
namespace App\Tenancy\Domain;

/**
 * The one place that flips the Doctrine connection's search_path into a
 * tenant schema and — crucially — always flips it back. Every Application/
 * console call site that used to inline the SET search_path / try / finally
 * dance goes through here, so the reset-to-public guarantee is enforced by
 * construction instead of by copy-paste discipline.
 *
 * Deliberately NOT used by TenantResolverListener (its switch must outlive
 * the method for the rest of the request) or TenantSchemaMigrator (sets the
 * bare schema without ", public" so the migration metadata table lands in
 * the tenant schema) — those two remain the documented, bespoke owners of
 * their own switching.
 */
interface TenantSchemaContextInterface
{
    public function resetToPublic(): void;

    /**
     * Runs $fn with search_path set to "<schema>, public", resetting to
     * public afterwards even when $fn throws.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function runInSchema(string $schemaName, callable $fn): mixed;
}
```

```php
namespace App\Tenancy\Infrastructure;

use App\Tenancy\Domain\TenantSchemaContextInterface;
use Doctrine\DBAL\Connection;

final class DbalTenantSchemaContext implements TenantSchemaContextInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resetToPublic(): void
    {
        $this->connection->executeStatement('SET search_path TO public');
    }

    public function runInSchema(string $schemaName, callable $fn): mixed
    {
        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($schemaName),
        ));

        try {
            return $fn();
        } finally {
            $this->resetToPublic();
        }
    }
}
```

services.yaml: `App\Tenancy\Domain\TenantSchemaContextInterface: '@App\Tenancy\Infrastructure\DbalTenantSchemaContext'`

- [ ] **Step 1: Failing integratietest schrijven** — `DbalTenantSchemaContextTest` (KernelTestCase; maak een throwaway schema à la `TenantResolverListenerTest`):

```php
public function testRunsCallableWithSchemaFirstOnSearchPathAndResetsAfterwards(): void
{
    $context = new DbalTenantSchemaContext($this->connection());

    $searchPathInside = $context->runInSchema('ctx_test', fn () => $this->currentSearchPath());

    self::assertStringContainsString('ctx_test', $searchPathInside);
    self::assertStringContainsString('public', $searchPathInside);
    self::assertStringNotContainsString('ctx_test', $this->currentSearchPath());
}

public function testResetsToPublicEvenWhenTheCallableThrows(): void
{
    $context = new DbalTenantSchemaContext($this->connection());

    try {
        $context->runInSchema('ctx_test', fn () => throw new \RuntimeException('boom'));
        self::fail('Expected the exception to propagate');
    } catch (\RuntimeException) {
    }

    self::assertStringNotContainsString('ctx_test', $this->currentSearchPath());
}

public function testReturnsTheCallablesReturnValue(): void
{
    $context = new DbalTenantSchemaContext($this->connection());

    self::assertSame(42, $context->runInSchema('ctx_test', fn () => 42));
}
```

(met `currentSearchPath()` = `$this->connection()->fetchOne('SHOW search_path')`, en setUp/tearDown die `CREATE SCHEMA ctx_test` / `DROP SCHEMA ... CASCADE` + reset doen.)

- [ ] **Step 2: Run FAIL → implementeren → PASS** (`make test-backend args="tests/Tenancy/Infrastructure/DbalTenantSchemaContextTest.php"`)

- [ ] **Step 3: Vangnet dichten vóór migratie — `CreateTenantUserCommandTest` schrijven (tegen de HUIDIGE code, moet direct groen zijn)**

`tenant-user:create` heeft nu nul testdekking; zonder deze test is "volledige suite groen" daar een lege claim. `CommandTester`-integratietest à la `SeedDevFixturesCommandTest`, met een via `ProvisionTenant` (of raw SQL à la `TenantResolverListenerTest`) aangemaakt throwaway-tenant-schema:

```php
public function testCreatesTheUserInsideTheTenantsOwnSchemaAndResetsSearchPath(): void
{
    // setUp provisionde tenant "cmd-test"; TenantName::asSchemaName() prefixt
    // met "tenant_" en vervangt "-" door "_", dus het schema heet
    // "tenant_cmd_test" (zie TenantName::SCHEMA_PREFIX).
    $tester = $this->commandTester('tenant-user:create');
    $exitCode = $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertSame(1, (int) $this->connection()->fetchOne(
        "SELECT COUNT(*) FROM tenant_cmd_test.tenant_users WHERE email = 'cli@test.nl'"
    ));
    self::assertStringNotContainsString('tenant_cmd_test', (string) $this->connection()->fetchOne('SHOW search_path'));
}

public function testFailsCleanlyAndResetsSearchPathForADuplicateEmail(): void
{
    $tester = $this->commandTester('tenant-user:create');
    $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);
    $exitCode = $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringNotContainsString('tenant_cmd_test', (string) $this->connection()->fetchOne('SHOW search_path'));
}

public function testFailsForAnUnknownSubdomainWithoutTouchingSearchPath(): void
{
    $tester = $this->commandTester('tenant-user:create');
    $exitCode = $tester->execute(['subdomain' => 'nope', 'email' => 'cli@test.nl', 'password' => 'secret']);

    self::assertSame(Command::FAILURE, $exitCode);
}
```

(Helpers `commandTester()`/`connection()` naar het voorbeeld van `SeedDevFixturesCommandTest`/`MigrateTenantSchemasCommandTest`. setUp provisiont via `ProvisionTenant` — signatuur `__invoke(string $name, ?string $slug = null)`, dus `($provisionTenant)('Cmd Test', 'cmd-test')` — en tearDown dropt schema `tenant_cmd_test` CASCADE en ruimt de `tenants`-rij op.)

Run: `make test-backend args="tests/TenantUser/Infrastructure/Command"` → PASS tegen de huidige code. Commit apart.

- [ ] **Step 4: Call-sites migreren, één per commit, met de volledige suite groen na elk**

Patroon (voorbeeld `ProvisionTenantWithAdmin`): injecteer `TenantSchemaContextInterface $schemaContext` i.p.v. `Connection`, en vervang het SET/try/finally-blok door:

```php
$this->schemaContext->runInSchema($tenant->getSchemaName(), function () use ($email, $password): void {
    ($this->createTenantUser)($email, $password, [TenantUser::ROLE_TENANT_ADMIN]);
});
```

- `ListTenantUsers`: `return $this->schemaContext->runInSchema($tenant->getSchemaName(), fn () => ($this->listTenantUsersForCurrentTenant)());` — de voorafgaande `resetToPublic()` + tenant-lookup blijven.
- `CreateTenantUserForTenant`: idem met return.
- `CreateTenantUserCommand`: let op — de huidige `finally` reset óók in het CLI-foutpad; `runInSchema` doet dat identiek. De catch-op-Throwable voor de CLI-foutmelding blijft er omheen.
- `SeedDevFixturesCommand:136-162`: het switch/try/finally-blok vervangen; de dev/test-allowlist en alle fixture-inhoud blijven onaangeroerd.
- Reset-only-sites (`ProvisionTenant`, `ArchiveTenant`, `UnarchiveTenant`, `UpdateTenant`): `Connection`-injectie vervangen door `TenantSchemaContextInterface` en `executeStatement('SET search_path TO public')` → `resetToPublic()`. (`ProvisionTenant` houdt zijn `Connection` alleen als die elders in de klasse nodig is — check; zo niet, verwijder de dependency.) Herschrijf in dezelfde commit de bijbehorende unit-tests (`ArchiveTenantTest`/`UnarchiveTenantTest`/`UpdateTenantTest`) volgens de voorgeschreven verwachting uit de Files-sectie.
- **Niet aanraken:** `TenantResolverListener`, `TenantSchemaMigrator` (zie interface-docblock).

Na elke call-site: `make test-backend` volledig groen (de bestaande `TenantResolverListenerTest`, `TenantOwnUsersApiTest`-achtige suites en seed-tests zijn hier het vangnet — dit is het security-kritieke stuk uit de nulmeting §a4).

- [ ] **Step 5: Slot-verificatie + laatste commit**

Run: `grep -rn "SET search_path" api/src`
Expected: alleen nog `DbalTenantSchemaContext`, `TenantResolverListener`, `TenantSchemaMigrator`.

```bash
git add api
git commit -m "KOZ-<n>: TenantSchemaContext — search_path-switch/reset op één plek afgedwongen"
```

---

### Taak 9: Gedeelde image-policy voor profielfoto en login-image

**Files:**
- Create: `api/src/Shared/Domain/Storage/StoredImagePolicy.php`, `api/src/Shared/Domain/Storage/StoredImageErrorKeys.php`, `api/tests/Shared/Domain/Storage/StoredImagePolicyTest.php`
- Modify: `api/tests/ProfilePhoto/Application/UploadProfilePhotoTest.php`, `api/tests/Tenancy/Application/UploadTenantLoginImageTest.php` (eerst versterken — zie Step 1), daarna `api/src/ProfilePhoto/Application/UploadProfilePhoto.php`, `api/src/Tenancy/Application/UploadTenantLoginImage.php`, `api/src/Tenancy/Application/GetTenantLoginImage.php`

**Contract-waarschuwing (uit review):** bouw errorKeys NOOIT via string-concatenatie (`$prefix . '.empty'`) — het checkscript uit Taak 2 extraheert alleen volledige literals en zou dan stil dekking verliezen. Daarom neemt `assertValid` een `StoredImageErrorKeys`-object met drie **volledige** key-literals.

**Interfaces:**
- Produces:

```php
namespace App\Shared\Domain\Storage;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The upload rules ProfilePhoto (KOZ-32/33) and the tenant login image
 * (KOZ-34) deliberately share: same mime allowlist, same 5 MiB cap, same
 * random storage-key shape. The error-key prefix and human-readable
 * subject stay caller-supplied because each feature reports its own keys
 * (profilePhoto.error.* vs tenantSettings.error.*) — the policy is shared,
 * the per-endpoint error contract is not.
 */
final class StoredImagePolicy
{
    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MAX_SIZE_IN_BYTES = 5 * 1024 * 1024; // 5 MiB

    /** @var array<string, string> */
    public const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, string> */
    public const MIME_TYPES_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * @param string $subject Lowercase human phrase for the English message,
     *                        e.g. "profile photo" / "login image".
     * @param StoredImageErrorKeys $errorKeys The caller's three FULL error-key
     *        literals. Deliberately not a prefix + concatenation: the
     *        check-contracts script (Taak 2) only extracts complete
     *        `<domain>.error.<name>` literals, so concatenated keys would
     *        silently fall out of that safety net.
     */
    public static function assertValid(string $mimeType, string $contents, string $subject, StoredImageErrorKeys $errorKeys): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::create(
                sprintf('Unsupported %s mime type "%s".', $subject, $mimeType),
                $errorKeys->unsupportedMimeType,
                ['mimeType' => $mimeType],
            );
        }

        $sizeInBytes = strlen($contents);

        if ($sizeInBytes === 0) {
            throw ValidationException::create(
                sprintf('%s file is empty.', ucfirst($subject)),
                $errorKeys->empty,
            );
        }

        if ($sizeInBytes > self::MAX_SIZE_IN_BYTES) {
            throw ValidationException::create(
                sprintf('%s exceeds the maximum size of %d bytes.', ucfirst($subject), self::MAX_SIZE_IN_BYTES),
                $errorKeys->tooLarge,
                ['maxSizeInBytes' => self::MAX_SIZE_IN_BYTES],
            );
        }
    }

    /** e.g. storageKey('profile-photos', 7, 'image/png') => "profile-photos/7/<32 hex>.png" */
    public static function storageKey(string $directory, int $ownerId, string $mimeType): string
    {
        return sprintf('%s/%d/%s.%s', $directory, $ownerId, bin2hex(random_bytes(16)), self::EXTENSIONS_BY_MIME_TYPE[$mimeType]);
    }

    public static function mimeTypeForStorageKey(string $storageKey): string
    {
        $extension = strtolower(pathinfo($storageKey, \PATHINFO_EXTENSION));

        return self::MIME_TYPES_BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }
}
```

```php
namespace App\Shared\Domain\Storage;

/**
 * The three error keys an image-accepting feature reports for the shared
 * StoredImagePolicy checks — full literals per call site (see the policy's
 * docblock for why these are never built by concatenation).
 */
final class StoredImageErrorKeys
{
    public function __construct(
        public readonly string $unsupportedMimeType,
        public readonly string $empty,
        public readonly string $tooLarge,
    ) {
    }
}
```

**Belangrijk gedragscontract:** de resulterende Engelse messages, errorKeys en errorKeyParams zijn
byte-identiek aan vandaag (vergelijk: 'Unsupported profile photo mime type "%s".',
'Profile photo file is empty.', 'Login image exceeds the maximum size of %d bytes.', …). De
bestaande use-case-/API-tests bewaken dit; wijken ze af, dan is de helper fout — niet de test.

- [ ] **Step 1: Bestaande use-case-tests versterken — VÓÓR de swap, tegen de huidige code**

`UploadProfilePhotoTest` en `UploadTenantLoginImageTest` asserten nu alleen `expectException(ValidationException::class)` — te zwak om byte-behoud te bewijzen. Breid de bestaande faalpad-tests uit met message/key/params-asserts, overgeschreven **uit de huidige broncode** (niet uit dit plan), in try/catch-stijl:

```php
try {
    ($this->uploadProfilePhoto)(7, 'x.gif', 'image/gif', 'data');
    self::fail('Expected ValidationException');
} catch (ValidationException $exception) {
    self::assertSame('Unsupported profile photo mime type "image/gif".', $exception->getMessage());
    self::assertSame('profilePhoto.error.unsupportedMimeType', $exception->getErrorKey());
    self::assertSame(['mimeType' => 'image/gif'], $exception->getErrorKeyParams());
}
```

Idem voor `empty` en `tooLarge` in beide testklassen (zes faalpaden totaal). Run: PASS tegen de huidige code; commit apart. Deze asserts zijn daarna het externe bewijs dat de swap niets wijzigt.

- [ ] **Step 2: Failing unit-tests voor de policy schrijven** (per methode: happy path, elk van de drie faalpaden met message/key/params-assert voor beide featurekey-sets, storage-key-regex `#^profile-photos/7/[0-9a-f]{32}\.png$#`, mime-lookup incl. onbekende extensie → octet-stream).

- [ ] **Step 3: Run FAIL → implementeren → PASS** (`make test-backend args="tests/Shared/Domain/Storage"`)

- [ ] **Step 4: De drie use cases omzetten**

- `UploadProfilePhoto`: eigen constanten weg; met een klassenconstante voor de keys (volledige literals!):

```php
StoredImagePolicy::assertValid($mimeType, $contents, 'profile photo', new StoredImageErrorKeys(
    'profilePhoto.error.unsupportedMimeType',
    'profilePhoto.error.empty',
    'profilePhoto.error.tooLarge',
));
$storageKey = StoredImagePolicy::storageKey('profile-photos', $ownerId, $mimeType);
```

- `UploadTenantLoginImage`: idem met `'login image'`, keys `tenantSettings.error.unsupportedMimeType` / `.empty` / `.tooLarge` (volledige literals), directory `'tenant-login-images'`, owner `$tenant->getId()`.
- `GetTenantLoginImage`: eigen `MIME_TYPES_BY_EXTENSION` weg → `StoredImagePolicy::mimeTypeForStorageKey($storageKey)`.
- `GetProfilePhoto` blijft ongemoeid (leest mimeType uit de metadata-rij, geen map).

- [ ] **Step 5: Volledige suite + contract-check + commit**

Run: `make test-backend` én `node scripts/check-contracts.mjs` — het script moet nog steeds exact evenveel keys vinden als vóór deze taak (de Step-1-asserts + volledige literals bewijzen gedrags- én contractbehoud).

```bash
git add api
git commit -m "KOZ-<n>: StoredImagePolicy — gedeelde upload-regels voor profielfoto en login-image"
```

---

### Taak 10: ProfilePhoto-endpoint-handler — controller-tweeling ontdubbeld

**Files:**
- Create: `api/src/ProfilePhoto/Infrastructure/Http/ProfilePhotoEndpoint.php`
- Modify: `api/src/ProfilePhoto/Infrastructure/Controller/UploadProfilePhotoController.php`, `UploadTenantProfilePhotoController.php`, `GetProfilePhotoController.php`, `GetTenantProfilePhotoController.php`

**Interfaces:**
- Consumes: `UploadProfilePhoto`, `GetProfilePhoto`, `ProfilePhotoContentDisposition`, `ExceptionResponsePayload`.
- Produces:

```php
namespace App\ProfilePhoto\Infrastructure\Http;

/**
 * The HTTP <-> use-case translation the four profile-photo controllers
 * (admin + tenant realm, upload + get) previously each copy-pasted. The
 * controllers keep exactly one job: resolving the authenticated owner the
 * way their own firewall does (#[CurrentUser] User vs Security::getUser()
 * instanceof TenantUser) — everything after "we have an owner id" is
 * identical by construction here.
 */
final class ProfilePhotoEndpoint
{
    public function __construct(
        private readonly UploadProfilePhoto $uploadProfilePhoto,
        private readonly GetProfilePhoto $getProfilePhoto,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param string $logContext "user" (admin realm) or "tenant user" — keeps today's log lines intact. */
    public function handleUpload(Request $request, int $ownerId, string $logContext): JsonResponse
    public function handleGet(int $ownerId): Response
}
```

`handleUpload` bevat letterlijk het bestaande blok uit `UploadProfilePhotoController::__invoke` vanaf de `$request->files->get('photo')`-check t/m de 201-response (incl. het finfo-security-docblock en de bestaande log-message met `$logContext` geïnterpoleerd: `sprintf('Profile photo upload failed for %s {userId}: {message}', $logContext)`). `handleGet` bevat het bestaande blok uit `GetProfilePhotoController` (404 / 200 met Content-Type + Content-Disposition).

- [ ] **Step 1: Handler schrijven** (code 1-op-1 verhuizen, niets herformuleren).

- [ ] **Step 2: De vier controllers terugbrengen tot user-resolutie + delegatie**

```php
// UploadProfilePhotoController::__invoke
public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
{
    return $this->endpoint->handleUpload($request, $user->getId(), 'user');
}
```

```php
// UploadTenantProfilePhotoController::__invoke — de bestaande instanceof-guard blijft:
public function __invoke(Request $request): JsonResponse
{
    $user = $this->security->getUser();
    if (!$user instanceof TenantUser) {
        return new JsonResponse(
            ExceptionResponsePayload::withKey('Not authenticated as a tenant user.', 'profilePhoto.error.uploadFailed'),
            JsonResponse::HTTP_UNAUTHORIZED,
        );
    }

    return $this->endpoint->handleUpload($request, $user->getId(), 'tenant user');
}
```

Get-varianten analoog: `return $this->endpoint->handleGet($user->getId());`. **Let op: de 401-guards verschillen bewust per controller en blijven letterlijk zoals ze zijn** — de upload-variant retourneert een JSON-`ExceptionResponsePayload` (zie hierboven), maar `GetTenantProfilePhotoController` retourneert vandaag een kale `new Response('', Response::HTTP_UNAUTHORIZED)` zónder JSON-body. Kopieer dus niet de upload-guard naar de Get-controller:

```php
// GetTenantProfilePhotoController::__invoke — bestaande kale 401 behouden:
public function __invoke(): Response
{
    $user = $this->security->getUser();
    if (!$user instanceof TenantUser) {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }

    return $this->endpoint->handleGet($user->getId());
}
```

Routes, attributen (`#[Route]`, `#[IsGranted]`) en responses blijven exact gelijk.

- [ ] **Step 3: Volledige suite draaien** — `make test-backend args="tests/ProfilePhoto"` en daarna volledig; de bestaande API-tests voor beide realms zijn de gedragsbewaking. Geen nieuwe tests nodig (pure verplaatsing, gedrag al gedekt).

- [ ] **Step 4: Commit**

```bash
git add api/src/ProfilePhoto
git commit -m "KOZ-<n>: ProfilePhotoEndpoint — vier controllers gereduceerd tot user-resolutie + delegatie"
```

---

### Taak 11: Kleine opruiming

**Files:**
- Delete: `api/src/Entity/.gitignore`, `api/src/Repository/.gitignore` (de enige getrackte inhoud van de twee Symfony-skelet-mappen; daarmee verdwijnen de mappen zelf)
- Modify: `README.md` (alleen als daar naar verwijderde zaken verwezen wordt — check)

- [ ] **Step 1: Verifieer en verwijder de skelet-mappen**

Run: `git ls-files api/src/Entity api/src/Repository`
Expected: **precies twee getrackte bestanden**: `api/src/Entity/.gitignore` en `api/src/Repository/.gitignore` (Symfony-skeleton-restanten die de lege mappen in git houden). Dan:

```bash
git rm api/src/Entity/.gitignore api/src/Repository/.gitignore
rmdir api/src/Entity api/src/Repository
```

Expected: beide mappen weg; `git status` toont exact twee deletions. Staat er méér in de mappen dan die twee `.gitignore`-bestanden, stop dan en laat ze staan (meld het in de PR). Controleer daarna `grep -rn "App.Entity" api/config` — `doctrine.yaml` mapt mogelijk `App\Entity`; een mapping naar een niet-bestaande map is voor Doctrine onschadelijk zolang er geen entities in gemapt waren, maar verifieer dit door de volledige backend-suite te draaien vóór de commit. Faalt er iets op de mapping, zet dan de mapping-regel óók weg (zelfde commit) of draai de verwijdering terug en documenteer waarom.

- [ ] **Step 2: Beide suites + contracts + commit**

Run: `make test-backend`, `make npm args="run test"`, `node scripts/check-contracts.mjs`

```bash
git add -A
git commit -m "KOZ-<n>: lege Symfony-skeletmappen verwijderd"
```

---

## Afronding (na alle taken)

- [ ] Volledige verificatie: `make test-backend` && `make npm args="run test"` && `make npm args="run lint"` && `make npm args="run build"` && `node scripts/check-contracts.mjs` && de volledige smoke-test uit Taak 3 Step 8 op `koz-<n>.kozijnr.localhost` (beide realms, beide talen, incl. foutpaden).
- [ ] PR aanmaken naar `main` met: samenvatting per taak, verwijzing naar nulmeting + dit plan, expliciete "bewust niet gedaan"-lijst (uit de nulmeting), de melding dat CI vanaf deze PR bestaat, en twee voorgestelde follow-up-tickets: (1) coverage-drempel afdwingen (pcov in het backend-image + drempel in CI); (2) pre-existing oddity: `tenantUserCreateFailedMessage()` hergebruikt key `tenants.error.createFailed` met een afwijkende Engelse fallback ("Failed to create tenant user.") — bewust NIET meegefixt in deze refactor-PR.

## Bewust buiten scope

Zie nulmeting §Scopebesluiten: entity-merge User/TenantUser, login-formulieren, form-dialogen,
DTO-/repository-tweelingen, route-guard-merge, `#[MapRequestPayload]`, OpenAPI-codegen,
admin-profielfoto-endpoints/`app/demo/` verwijderen, sliding-expiry, `getTenant`-endpoint,
coverage-drempel (follow-up-ticket voorstellen in de PR-beschrijving).

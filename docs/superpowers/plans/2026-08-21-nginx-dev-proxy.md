# Nginx dev-proxy / API-first frontend / Valet removal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Laravel Valet with one shared nginx container routing `*.kozijnr.localhost` (and `*.koz-<n>.kozijnr.localhost` per worktree) to the right stack, and make the Next.js frontend a pure REST client of `api.<base>`.

**Architecture:** A standalone compose project `kozijnr-proxy` (nginx on host :80, external docker network `kozijnr-proxy`) routes by hostname regex to `${COMPOSE_PROJECT_NAME}-frontend:3000` / `-backend:8000` aliases that each app stack registers on that network. The backend gains a real `CorsListener` and derives tenant/admin context from the `Origin` header when reached on `api.<base>`; the frontend loses its `app/api/**` pass-through routes and calls `api.<base>` directly with credentials.

**Tech Stack:** Docker Compose, nginx:alpine, Symfony 7 (PHP 8.5, PHPUnit), Next.js 16 (vitest), bash, Make.

**Spec:** `docs/superpowers/specs/2026-08-21-nginx-dev-proxy-design.md`

## Global Constraints

- Backend tests run inside the container: `make test-backend args="..."` (forces `APP_ENV=test`; `.env.test` has `APP_BASE_DOMAIN=localhost`).
- Frontend tests: `make npm args="test"`; lint: `make npm args="run lint"`; types: `make npm args="exec tsc -- --noEmit"`.
- Commit after each task on `main`, message prefix `Infra:`; trailer `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>` + `Claude-Session: https://claude.ai/code/session_016tQXmwsuzPEJQWXGb672X5`.
- Domain defaults: `APP_BASE_DOMAIN=kozijnr.localhost` (main), `koz-<n>.kozijnr.localhost` (worktree). Proxy host port `PROXY_PORT` default `80`.
- No Valet references may remain anywhere except git history.

---

### Task 1: Backend — remove Valet code and config

**Files:**
- Delete: `api/src/Tenancy/Infrastructure/Valet/` (3 files), `api/tests/Tenancy/Infrastructure/Valet/` (2 files)
- Modify: `api/config/services.yaml`, `api/config/services_dev.yaml`, `api/.env`, `api/src/Tenancy/Infrastructure/TenantResolverListener.php` (comment), `api/src/Tenancy/Domain/Subdomain.php` (comment), `api/src/TenantUser/Infrastructure/Security/TenantApiTokenCookie.php` (comment)

- [ ] **Step 1: Delete Valet classes and tests**

```bash
git rm -r api/src/Tenancy/Infrastructure/Valet api/tests/Tenancy/Infrastructure/Valet
```

- [ ] **Step 2: Strip config**

`api/config/services.yaml` `parameters:` keeps only:
```yaml
parameters:
    # Base domain requests are matched against to find the tenant/admin/api
    # subdomain (App\Tenancy\Domain\Subdomain). Locally "kozijnr.localhost"
    # (main) or "koz-<n>.kozijnr.localhost" (worktree) — see README.md
    # "Local domains via nginx"; in production the real apex, e.g. kozijnr.nl.
    app.tenancy.base_domain: '%env(APP_BASE_DOMAIN)%'
    app.tenancy.migrations_path: '%kernel.project_dir%/migrations-tenant'
```
`api/config/services_dev.yaml` becomes only the `_defaults` block (keep the file; later tasks don't need it but removing it is fine too — keep it minimal):
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
```
`api/.env`: drop `BACKEND_PORT`; set `APP_BASE_DOMAIN=kozijnr.localhost`, `DEFAULT_URI=http://api.kozijnr.localhost`; rewrite the `app/tenancy` comment block to mention nginx instead of Valet.

- [ ] **Step 3: Fix comments that mention Valet** in `TenantResolverListener.php:~85`, `Subdomain.php:~27-30`, `TenantApiTokenCookie.php:~19-20` — replace "Valet `*.test`" wording with "nginx `*.kozijnr.localhost`" (plain HTTP locally).

- [ ] **Step 4: Run the suite**

Run: `make up && make test-backend`
Expected: all green, no "class not found" for Valet.

- [ ] **Step 5: Verify no leftovers**

Run: `grep -rni valet api/src api/config api/.env api/tests` → no output.

- [ ] **Step 6: Commit** — `Infra: verwijder Valet-code uit de backend`

---

### Task 2: Backend — `CorsListener`

**Files:**
- Create: `api/src/Tenancy/Infrastructure/CorsListener.php`
- Test: `api/tests/Tenancy/Infrastructure/CorsListenerTest.php`
- Modify: `api/config/services.yaml` (explicit args — autowire can't resolve `$baseDomain`)

**Interfaces:**
- Produces: `App\Tenancy\Infrastructure\CorsListener::__construct(string $baseDomain)`; subscribes `kernel.request` prio 250 (before `TenantResolverListener` at 100) and `kernel.response`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace App\Tests\Tenancy\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CorsListenerTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    public function testAllowedOriginGetsCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
        ]);
        $r = $client->getResponse();
        self::assertSame('http://admin.localhost', $r->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $r->headers->get('Access-Control-Allow-Credentials'));
        self::assertStringContainsString('Origin', (string) $r->headers->get('Vary'));
    }

    public function testForeignOriginGetsNoCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://evil.example',
        ]);
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testLookalikeOriginIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.localhost.evil.example',
        ]);
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testPreflightIsAnsweredWithoutRouting(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/admin/login', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $r = $client->getResponse();
        self::assertSame(204, $r->getStatusCode());
        self::assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $r->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, Authorization', $r->headers->get('Access-Control-Allow-Headers'));
    }
}
```

- [ ] **Step 2: Run** `make test-backend args="tests/Tenancy/Infrastructure/CorsListenerTest.php"` → FAIL (no headers / 404 on OPTIONS).

- [ ] **Step 3: Implement**

```php
<?php
namespace App\Tenancy\Infrastructure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS for browser clients on sibling subdomains of the base domain
 * (admin.<base>, <tenant>.<base>) calling this API on api.<base>.
 */
final class CorsListener implements EventSubscriberInterface
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    private const ALLOWED_HEADERS = 'Content-Type, Authorization';

    public function __construct(private readonly string $baseDomain) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 250],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $request = $event->getRequest();
        $origin = $request->headers->get('Origin');
        if ($origin === null || !$this->isAllowedOrigin($origin) || $request->getMethod() !== 'OPTIONS') { return; }
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->addHeaders($response, $origin);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $origin = $event->getRequest()->headers->get('Origin');
        if ($origin === null || !$this->isAllowedOrigin($origin)) { return; }
        $this->addHeaders($event->getResponse(), $origin);
    }

    public function isAllowedOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host)) { return false; }
        $host = strtolower($host);
        $base = strtolower($this->baseDomain);
        return $host === $base || str_ends_with($host, '.' . $base);
    }

    private function addHeaders(Response $response, string $origin): void
    {
        $h = $response->headers;
        $h->set('Access-Control-Allow-Origin', $origin);
        $h->set('Access-Control-Allow-Credentials', 'true');
        $h->set('Access-Control-Allow-Methods', self::ALLOWED_METHODS);
        $h->set('Access-Control-Allow-Headers', self::ALLOWED_HEADERS);
        $h->set('Vary', 'Origin', false);
    }
}
```
`services.yaml`:
```yaml
    App\Tenancy\Infrastructure\CorsListener:
        arguments:
            $baseDomain: '%app.tenancy.base_domain%'
```

- [ ] **Step 4: Run** same test → PASS. Then full `make test-backend` → green.
- [ ] **Step 5: Commit** — `Infra: CorsListener voor browser-clients op api.<base>`

---

### Task 3: Backend — tenant/admin context from `Origin` on `api.<base>`

**Files:**
- Modify: `api/src/Tenancy/Infrastructure/TenantResolverListener.php`
- Test: `api/tests/Tenancy/Infrastructure/TenantResolverListenerTest.php` (add cases), `api/tests/Tenancy/Infrastructure/AdminRouteGuardListenerTest.php` (add case)

**Interfaces:**
- Produces: on `Host` = `api.<base>`, `TenantResolverListener` resolves using `Subdomain::extractFrom(parse_url(Origin, HOST), base)`; no/invalid Origin → public.

- [ ] **Step 1: Failing tests** (append to `TenantResolverListenerTest`; mirror the existing assertions style in that file — check how it reads `search_path`, e.g. via an existing `/api/health` JSON field; use the same mechanism)

```php
    public function testOriginHeaderSelectsTenantOnApiHost(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.' . self::BASE_DOMAIN,
        ]);
        self::assertResponseIsSuccessful();
        // same search_path assertion the existing tenant test uses -> tenant_a, public
    }

    public function testUnknownTenantInOriginOnApiHostIs404(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://nope.' . self::BASE_DOMAIN,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOriginIsIgnoredOnNonApiHost(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'tenant-b.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.' . self::BASE_DOMAIN,
        ]);
        // search_path -> tenant_b
    }

    public function testForeignOriginOnApiHostStaysPublic(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.localhost.evil.example',
        ]);
        // search_path -> public
    }
```
And in `AdminRouteGuardListenerTest`:
```php
    public function testAdminOriginOnApiHostReachesAdminRoutes(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
        ]);
        // not 404 (guard passed); 401 without session is what we expect
        self::assertNotSame(404, $this->client->getResponse()->getStatusCode());
    }
```

- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** in `onKernelRequest`, replacing the `RESERVED_API` early return:

```php
        if ($subdomain === Subdomain::RESERVED_API) {
            // Browser clients live on admin.<base> / <tenant>.<base> and call
            // this API cross-origin on api.<base>; the browser-set Origin
            // header tells us which context they belong to. Non-browser
            // clients without Origin stay on the public schema.
            $subdomain = $this->subdomainFromOrigin($request);
            if ($subdomain === null || $subdomain === Subdomain::RESERVED_API) {
                return;
            }
            if ($subdomain === Subdomain::RESERVED_ADMIN) {
                $request->attributes->set(self::ADMIN_REQUEST_ATTRIBUTE, true);
                return;
            }
        }
```
with
```php
    private function subdomainFromOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null) { return null; }
        $host = parse_url($origin, PHP_URL_HOST);
        return is_string($host) ? Subdomain::extractFrom($host, $this->baseDomain) : null;
    }
```
(Restructure so the admin branch is checked after Origin resolution; keep `$request = $event->getRequest();` at the top.)

- [ ] **Step 4: Run** both test files, then full suite → green.
- [ ] **Step 5: Commit** — `Infra: tenant/admin-context uit Origin op api.<base>`

---

### Task 4: Frontend — API service layer, drop route handlers

**Files:**
- Modify: `web/lib/api.ts`, `web/proxy.ts`, `web/components/nav-user.tsx`, `web/next.config.ts`
- Create: `web/lib/api-base-url.ts`, `web/lib/api-base-url.test.ts`
- Delete: `web/app/api/` (login, logout, admin/login, admin/logout)

**Interfaces:**
- Produces: `apiBaseUrl(host: string, protocol?: string): string` → `http://api.<rest>`; `lib/api.ts` exports `login`, `adminLogin`, `logout`, `adminLogout` (all `fetch(`${apiBaseUrl(window.location.host, window.location.protocol)}/api/...`, { credentials: "include" })`).

- [ ] **Step 1: Failing test** `web/lib/api-base-url.test.ts`

```ts
import { describe, expect, it } from "vitest"
import { apiBaseUrl } from "./api-base-url"

describe("apiBaseUrl", () => {
  it("replaces the first label with api", () => {
    expect(apiBaseUrl("admin.kozijnr.localhost")).toBe("http://api.kozijnr.localhost")
    expect(apiBaseUrl("acme.koz-16.kozijnr.localhost")).toBe("http://api.koz-16.kozijnr.localhost")
  })
  it("keeps an explicit port and protocol", () => {
    expect(apiBaseUrl("admin.kozijnr.localhost:8080", "https:")).toBe("https://api.kozijnr.localhost:8080")
  })
  it("is idempotent on an api host", () => {
    expect(apiBaseUrl("api.kozijnr.localhost")).toBe("http://api.kozijnr.localhost")
  })
})
```
- [ ] **Step 2: Run** `make npm args="test"` → FAIL (module missing).
- [ ] **Step 3: Implement** `web/lib/api-base-url.ts`

```ts
/** Derive the backend origin (api.<base>) from the host the page was served on. */
export function apiBaseUrl(host: string, protocol: string = "http:"): string {
  const [hostname, port] = host.split(":")
  const labels = hostname.split(".")
  labels[0] = "api"
  return `${protocol}//${labels.join(".")}${port ? `:${port}` : ""}`
}
```
`web/lib/api.ts`: add `function backendUrl(path: string) { return `${apiBaseUrl(window.location.host, window.location.protocol)}${path}` }`; `postCredentials` fetches `backendUrl(path)` with `credentials: "include"`; add
```ts
export async function logout(): Promise<void> { await post("/api/logout") }
export async function adminLogout(): Promise<void> { await post("/api/admin/logout") }
async function post(path: string): Promise<void> {
  try { await fetch(backendUrl(path), { method: "POST", credentials: "include" }) } catch { /* best effort */ }
}
```
`nav-user.tsx`: `import { adminLogout, logout } from "@/lib/api"`; `await (context === "admin" ? adminLogout() : logout())`.
`proxy.ts`: remove `PUBLIC_API_PATHS` block; `hasValidAdminSession` calls `sendBackendRequest({ host: backendInternalHost, port: backendInternalPort, path: "/api/admin/me", method: "GET", tenantHost: `api.${baseDomain}`, headers: { Cookie: cookieHeader, Origin: `http://${hostname}` } })` where `baseDomain = hostname.split(".").slice(1).join(".")`. Trim the docblock's rework-history to a short current description.
`next.config.ts`: drop `allowedDevOrigins` (default allows `**.localhost`), replace comment.
Delete `web/app/api` via `git rm -r web/app/api`.

- [ ] **Step 4: Run** `make npm args="test"`, `make npm args="run lint"`, `make npm args="exec tsc -- --noEmit"` → green.
- [ ] **Step 5: Commit** — `Infra: frontend als pure REST-client van api.<base>`

---

### Task 5: Shared proxy stack

**Files:**
- Create: `docker/proxy/docker-compose.yml`, `docker/proxy/nginx.conf`, `docker/proxy/.env.example`
- Modify: `Makefile`

- [ ] **Step 1: compose**

```yaml
# Shared dev reverse proxy: one nginx for the main checkout and every
# worktree. Start with `make proxy-up` (from any checkout). Routes by hostname
# over the external network `kozijnr-proxy`, which every app stack joins.
name: kozijnr-proxy
services:
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "${PROXY_PORT:-80}:80"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    networks: [proxy]
networks:
  proxy:
    external: true
    name: kozijnr-proxy
```
- [ ] **Step 2: nginx.conf**

```nginx
# Hostname-based routing for every Kozijnr stack on the kozijnr-proxy network.
# Upstreams are resolved per request via Docker DNS so stacks can start/stop
# without restarting this proxy; a stopped stack yields 502.
resolver 127.0.0.11 valid=5s ipv6=off;

map $http_upgrade $connection_upgrade { default upgrade; '' close; }

# api.koz-<n>.kozijnr.localhost -> that worktree's backend
server {
    listen 80;
    server_name ~^api\.(?<project>koz-\d+)\.kozijnr\.localhost$;
    location / { include /etc/nginx/conf.d/proxy-common.inc; proxy_pass http://$project-backend:8000; }
}
# api.kozijnr.localhost -> main backend
server {
    listen 80;
    server_name api.kozijnr.localhost;
    location / { include /etc/nginx/conf.d/proxy-common.inc; set $up kozijnr-backend; proxy_pass http://$up:8000; }
}
# <anything>.koz-<n>.kozijnr.localhost -> that worktree's frontend
server {
    listen 80;
    server_name ~^[^.]+\.(?<project>koz-\d+)\.kozijnr\.localhost$;
    location / { include /etc/nginx/conf.d/proxy-common.inc; proxy_pass http://$project-frontend:3000; }
}
# <anything>.kozijnr.localhost -> main frontend
server {
    listen 80;
    server_name ~^[^.]+\.kozijnr\.localhost$;
    location / { include /etc/nginx/conf.d/proxy-common.inc; set $up kozijnr-frontend; proxy_pass http://$up:3000; }
}
server {
    listen 80 default_server;
    return 404 "kozijnr-proxy: unknown host. Use admin|api|<tenant>[.koz-<n>].kozijnr.localhost\n";
}
```
Plus `docker/proxy/proxy-common.inc` (mount it too):
```nginx
proxy_http_version 1.1;
proxy_set_header Host $host;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection $connection_upgrade;
proxy_read_timeout 300s;
```
(Using `set $up` + variable in `proxy_pass` forces runtime DNS resolution for the static names too.)

- [ ] **Step 3: Makefile targets**

```make
PROXY_COMPOSE := docker compose -f docker/proxy/docker-compose.yml

## Start the shared nginx reverse proxy (idempotent). Creates the external
## network it needs. All stacks (main + worktrees) share this one proxy.
proxy-up:
	@docker network inspect kozijnr-proxy >/dev/null 2>&1 || docker network create kozijnr-proxy
	$(PROXY_COMPOSE) up -d

proxy-down:
	$(PROXY_COMPOSE) down

proxy-logs:
	$(PROXY_COMPOSE) logs -f

up: ensure-env proxy-up
	$(COMPOSE) up -d
```
Add `proxy-up proxy-down proxy-logs` to `.PHONY`; remove `valet-sync valet-watch worktree-valet-teardown` targets and PHONY entries.

- [ ] **Step 4: Verify** `make proxy-up && docker compose -f docker/proxy/docker-compose.yml exec nginx nginx -t` → "syntax is ok"; `curl -s http://localhost/ ` → 404 text.
- [ ] **Step 5: Commit** — `Infra: gedeelde nginx dev-proxy (docker/proxy)`

---

### Task 6: App stack wiring + worktree env script

**Files:**
- Modify: `docker-compose.yml`, `scripts/setup-worktree-env.sh`

- [ ] **Step 1: docker-compose.yml** — rewrite header comment (nginx, not Valet). Services:

```yaml
  backend:
    ...
    # no `ports:` — reachable only via the shared proxy on api.<base>
    environment:
      APP_ENV: dev
      DATABASE_URL: "postgresql://app:app@database:5432/app?serverVersion=16&charset=utf8"
      APP_BASE_DOMAIN: "${APP_BASE_DOMAIN:-kozijnr.localhost}"
    networks:
      default: {}
      proxy:
        aliases: ["${COMPOSE_PROJECT_NAME:-kozijnr}-backend"]
  frontend:
    ...
    # no `ports:`
    environment:
      WATCHPACK_POLLING: "true"
    networks:
      default: {}
      proxy:
        aliases: ["${COMPOSE_PROJECT_NAME:-kozijnr}-frontend"]
networks:
  proxy:
    external: true
    name: kozijnr-proxy
```
Keep database `ports: "${DATABASE_PORT:-5432}:5432"`.

- [ ] **Step 2: setup-worktree-env.sh** — header comment rewritten; `.env` content:
```
COMPOSE_PROJECT_NAME=koz-${N}
DATABASE_PORT=$((5432+N))
APP_BASE_DOMAIN=koz-${N}.kozijnr.localhost
```
Echo the URLs `http://admin.koz-${N}.kozijnr.localhost`, `http://api.koz-${N}.kozijnr.localhost`; delete the whole `valet` block.

- [ ] **Step 3: Verify main** — `make down && make up`; `curl -s --resolve api.kozijnr.localhost:80:127.0.0.1 http://api.kozijnr.localhost/api/health` → 200 JSON; browser `http://admin.kozijnr.localhost/login` renders.
- [ ] **Step 4: Commit** — `Infra: app-stack op het kozijnr-proxy netwerk, worktree-env zonder poorten`

---

### Task 7: Scripts, skills, README cleanup

**Files:**
- Delete: `scripts/valet-sync.sh`, `scripts/valet-watch.sh`, `scripts/valet-watch.test.sh`, `scripts/teardown-worktree-valet.sh`
- Modify: `README.md`, `.claude/skills/asana-user-review/SKILL.md`

- [ ] **Step 1:** `git rm scripts/valet-*.sh scripts/teardown-worktree-valet.sh`
- [ ] **Step 2: README** — replace section "Local domains via Laravel Valet (KOZ-12)" (lines ~149-308) with "Local domains via nginx": what the proxy is, `make proxy-up`, domain table (main/worktree × api/admin/tenant), `*.localhost` resolves in browsers, `curl --resolve` example, `PROXY_PORT` override, "port 80 in use? stop Valet: `valet stop`". Update "Per-worktree test environments", "Verifying it works", "Super admin (KOZ-8)" curl examples to `api.<base>` + `-H 'Origin: http://admin.<base>'`; update "Make targets" list; remove "Testing multiple subdomains locally" Valet-less workaround (it's the default now). Update the `Requirements` line about Valet.
- [ ] **Step 3: SKILL.md** — remove the Valet teardown sentences in steps (line ~45) and the pitfalls bullet (~76); keep `docker compose -p koz-<n> down`.
- [ ] **Step 4:** `grep -rni valet --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=.worktrees --exclude-dir=docs .` → only the spec/plan under docs.
- [ ] **Step 5: Commit** — `Infra: Valet-scripts en -docs vervangen door nginx-docs`

---

### Task 8: End-to-end verification (main + worktree side by side)

- [ ] Main: `make up`; login on `http://admin.kozijnr.localhost/login` with the super-admin (README "Super admin"); dashboard loads; logout works.
- [ ] Tenant: `make console args="tenant:provision acme"` (+ a tenant user per README); `http://acme.kozijnr.localhost/login` → login → dashboard.
- [ ] Worktree: `cd .worktrees/koz-16 && make up` (generates `.env` for koz-16, no Valet); `http://admin.koz-16.kozijnr.localhost/login` renders while main still works; `curl --resolve api.koz-16.kozijnr.localhost:80:127.0.0.1 http://api.koz-16.kozijnr.localhost/api/health` → 200.
- [ ] Record results in the final report; no commit needed unless fixes were required.

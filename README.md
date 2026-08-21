# Kozijnr

Monorepo containing:

- `api/` — backend, Symfony (PHP 8.5)
- `web/` — frontend, Next.js (Node 24, latest stable LTS)
- `docker/` — Dockerfiles for the local dev environment
- `docker-compose.yml` — orchestrates backend, frontend and database for local development
- `Makefile` — thin wrappers around the `docker compose` commands below

## Requirements

- Docker Desktop (or an equivalent Docker Engine + Compose v2 install)

Nothing else needs to be installed on the host — PHP, Composer, Node, npm and
nginx all run inside containers. Local hostnames use `*.kozijnr.localhost`,
which every browser resolves to `127.0.0.1` by itself, so there is no DNS or
`/etc/hosts` setup either. Port 80 on the host must be free for the shared
proxy (if Laravel Valet or another local web server owns it, stop that first
or see `PROXY_PORT` under "Local domains via nginx").

## Quick start

```bash
make up
```

This generates the root `.env` for this checkout (see "Per-worktree test
environments"), starts the shared nginx proxy if it isn't running yet, builds
the images (first run only) and starts everything in the background:

- REST API (Symfony): http://api.kozijnr.localhost — health check at http://api.kozijnr.localhost/api/health
- Super-admin frontend (Next.js): http://admin.kozijnr.localhost
- Tenant frontend (Next.js): http://<tenant>.kozijnr.localhost — once a tenant exists, see "Provisioning tenants"
- Database (PostgreSQL 16): localhost:5432 (user `app`, password `app`, database `app`)

Follow logs with:

```bash
make logs
```

Stop everything with:

```bash
make down
```

## Make targets

| Target | What it does |
| --- | --- |
| `make up` | Start the stack in the background (generates `.env`, starts the shared proxy, builds images if needed) |
| `make down` | Stop and remove the stack's containers |
| `make build` | Build (or rebuild) all images |
| `make rebuild` | Rebuild images from scratch and restart the stack |
| `make restart` | Restart all services |
| `make logs` | Tail logs for all services |
| `make ps` | Show status of the stack's containers |
| `make sh-backend` | Open a shell in the backend container |
| `make sh-frontend` | Open a shell in the frontend container |
| `make composer args="require foo/bar"` | Run a composer command in the backend container |
| `make console args="cache:clear"` | Run a `bin/console` command in the backend container |
| `make npm args="run lint"` | Run an npm command in the frontend container |
| `make test-backend args="tests/Tenancy"` | Run the backend PHPUnit suite (or a subset) in the backend container |
| `make worktree-env n=12` | (Re)generate `.env` for KOZ-12 (`n=main` for the main checkout) |
| `make proxy-up` / `proxy-down` / `proxy-logs` | Start / stop / tail the shared nginx proxy (see "Local domains via nginx") |

These are thin wrappers around `docker compose` — nothing here does more than the
target's one-line `docker compose ...` invocation, see the `Makefile` itself.

## Development workflow

- Both `api/` and `web/` are bind-mounted into their containers, so editing code on the
  host is picked up live:
  - Backend: PHP's built-in server (`php -S`) re-evaluates PHP files on every request — no restart needed.
  - Frontend: Next.js dev server hot-reloads on file changes.
- Dependencies (`vendor/` for the backend, `node_modules/` for the frontend) live in
  named Docker volumes rather than being bind-mounted from the host. This means:
  - You don't need PHP/Composer or Node/npm installed locally at all.
  - Running `composer require ...` or `npm install ...` should be done *inside* the
    container (`make composer args="require ..."` / `make sh-frontend` then `npm install ...`)
    so the lockfile and the container's dependency volume stay in sync.
  - If you change `composer.lock` or `package-lock.json` from the host (e.g. after a
    `git pull`), run `make rebuild` (or just restart the affected service) so the
    entrypoint script reinstalls dependencies.

## Per-worktree test environments

Each git worktree (typically one per KOZ ticket) runs its own isolated copy
of the stack — different container names, volumes, database port and
hostnames — so multiple tickets can be developed and tested locally at the
same time without conflicts. All of them share the single nginx proxy (next
section), which tells them apart by hostname:

| | main checkout | worktree for KOZ-`<n>` (e.g. 12) |
| --- | --- | --- |
| API | `api.kozijnr.localhost` | `api.koz-12.kozijnr.localhost` |
| Admin frontend | `admin.kozijnr.localhost` | `admin.koz-12.kozijnr.localhost` |
| Tenant frontend | `<tenant>.kozijnr.localhost` | `<tenant>.koz-12.kozijnr.localhost` |
| Database (host port) | `5432` | `5432 + <n>` = `5444` |
| Compose project | `kozijnr` | `koz-12` |

The compose project name namespaces containers and volumes (KOZ-12's backend
becomes `koz-12-backend-1`, its database volume `koz-12_database_data`) and
is also the name the proxy routes to on the shared `kozijnr-proxy` docker
network (`koz-12-backend`, `koz-12-frontend`). Frontend and backend publish
no host ports at all — the proxy is the only way in.

This is set up automatically: `make up` derives the environment from the
current branch (`main`, or `koz-<n>`) and generates the root `.env` on the
fly via `scripts/setup-worktree-env.sh` if one doesn't exist yet. So in a
fresh `koz-<n>` worktree, a single

```bash
make up
```

is enough. If `.env` already exists it is left untouched — `make up` never
overwrites it. On any other branch name with no `.env`, `make up` fails with
an explanation instead of guessing; generate one explicitly:

```bash
make worktree-env n=12     # KOZ-12's environment, regardless of branch name
make worktree-env n=main   # the main checkout's environment
```

The generated `.env` (gitignored, one per worktree — see `.env.example`)
holds `COMPOSE_PROJECT_NAME`, `DATABASE_PORT` and `APP_BASE_DOMAIN`; the
latter is passed into the backend container so it resolves tenant/admin/api
subdomains, CORS origins and cookie scope against the right base domain.

## Local domains via nginx

One shared nginx container (`docker/proxy/`, compose project `kozijnr-proxy`)
listens on host port 80 and reverse-proxies every stack by hostname:

```
api.kozijnr.localhost             -> main backend   (kozijnr-backend:8000)
admin|<tenant>.kozijnr.localhost  -> main frontend  (kozijnr-frontend:3000)
api.koz-<n>.kozijnr.localhost     -> KOZ-n backend  (koz-<n>-backend:8000)
*.koz-<n>.kozijnr.localhost       -> KOZ-n frontend (koz-<n>-frontend:3000)
```

Every app stack joins the external docker network `kozijnr-proxy` with those
aliases (see `docker-compose.yml`); nginx resolves them per request through
Docker's DNS, so stacks can be started and stopped at will without touching
the proxy — a stack that isn't running just answers 502 on its hostnames.
Tenant subdomains need no registration anywhere: the wildcard matches.

`make up` starts the proxy automatically (`make proxy-up` does it on its own,
`make proxy-down` stops it, `make proxy-logs` tails it). It only needs to be
running once, no matter how many worktrees are up.

- **Browsers** resolve `*.localhost` to `127.0.0.1` themselves — nothing to
  configure.
- **curl** does not. Pass `--resolve`:

  ```bash
  curl --resolve api.kozijnr.localhost:80:127.0.0.1 http://api.kozijnr.localhost/api/health
  ```

- **Port 80 taken?** Copy `docker/proxy/.env.example` to `docker/proxy/.env`
  and set `PROXY_PORT=8080` (or anything free). URLs then carry the port:
  `http://admin.kozijnr.localhost:8080`. Laravel Valet, if installed, owns
  port 80 while running — `valet stop` frees it.

### How the frontend talks to the API

The frontend is a pure REST client. Pages on `admin.<base>` and
`<tenant>.<base>` call `http://api.<base>/api/...` directly from the browser
(`web/lib/api.ts`, with `credentials: "include"`); there are no API routes in
the Next.js app. Two backend pieces make that cross-origin-but-same-site setup
work, in every environment (production is `admin.kozijnr.nl` → `api.kozijnr.nl`):

- `App\Tenancy\Infrastructure\CorsListener` allows exactly the origins on the
  base domain (`admin.<base>`, `<tenant>.<base>`) and answers preflights.
- `TenantResolverListener` derives the tenant/admin context of a request on
  `api.<base>` from the browser-set `Origin` header — `Origin:
  http://acme.<base>` means "tenant acme", `Origin: http://admin.<base>` means
  "admin". No `Origin` (curl, server-to-server) means the public schema.
- Session/token cookies are issued with `Domain=<base>` so they reach both
  `api.<base>` and the frontend hosts (whose server-side route guard,
  `web/proxy.ts`, checks them before rendering a protected page).

### Verifying it works

With the stack running (`make up`):

```bash
R="--resolve api.kozijnr.localhost:80:127.0.0.1"
curl $R http://api.kozijnr.localhost/api/health                                            # 200, public schema
make console args="tenant:provision acme"
curl $R -H 'Origin: http://acme.kozijnr.localhost'  http://api.kozijnr.localhost/api/health  # 200, search_path = tenant_acme, public
curl $R -H 'Origin: http://admin.kozijnr.localhost' http://api.kozijnr.localhost/api/health  # 200, public schema, admin context
```

and in a browser: http://admin.kozijnr.localhost/login, http://acme.kozijnr.localhost/login.
For a worktree, substitute `koz-<n>.kozijnr.localhost` for `kozijnr.localhost`.

## Multi-tenancy: subdomain → schema resolution

Each tenant's data lives in its own Postgres schema, fully isolated from
other tenants at the database level. On every request, `App\Tenancy\Infrastructure\TenantResolverListener`
(a `kernel.request` listener, see `api/src/Tenancy/`) does the following, in order:

1. Resets the Doctrine connection's `search_path` to `public` unconditionally — so a
   request never inherits a schema left behind by a previous one (relevant if this
   app is ever run under a persistent-worker runtime such as FrankenPHP/RoadRunner,
   where the same PHP process/connection handles many requests; today's `php -S`
   dev server and standard PHP-FPM already start clean per request, but the reset
   makes that guarantee explicit rather than assumed).
2. Extracts the subdomain from the request's `Host` header (via `App\Tenancy\Domain\Subdomain`),
   relative to the `APP_BASE_DOMAIN` env var (`kozijnr.localhost` locally,
   `koz-<n>.kozijnr.localhost` per worktree; set to the real apex domain, e.g.
   `kozijnr.nl`, in production).
   - No subdomain, or a request to the base domain itself → stays on the `public`
     schema. No tenant lookup happens.
   - `admin` subdomain (e.g. `admin.localhost`, `admin.kozijnr.nl` in production) →
     recognized as the reserved, tenant-independent super-admin domain (see "Super
     admin" below) and stays on the `public` schema. Never looked up in `tenants`,
     and can never itself be provisioned as a tenant name — `TenantName` rejects
     `admin` outright.
   - `api` subdomain (`api.kozijnr.localhost` locally, `api.kozijnr.nl` in production)
     → the REST API's own hostname: also reserved, also rejected by `TenantName` as
     a tenant name. On its own it stays on the `public` schema; when the request
     carries a browser `Origin` on a sibling subdomain (`http://acme.<base>`,
     `http://admin.<base>`), the tenant/admin context is taken from that Origin
     instead — see "How the frontend talks to the API" above.
   - Any other subdomain → looked up in the `tenants` table (public schema, see the
     `Version20260819053803` migration) for its `subdomain` → `schema_name` mapping.
3. Unknown subdomain → `404 Not Found`. There is no fallback to `public` or to any
   other schema for a subdomain that doesn't match a row in `tenants`.
4. Known subdomain → `SET search_path TO "<schema_name>", public` on the connection,
   for the rest of that request.

This ticket (KOZ-6) only wires up the resolution + schema switch, backed by
`api/tests/Tenancy/Infrastructure/TenantResolverListenerTest.php`, which proves data
in one tenant schema is never visible from a request to another tenant's subdomain.
Creating tenant schemas and running per-tenant migrations is handled by
`tenant:provision` — see "Provisioning tenants" below (KOZ-7).

### Provisioning tenants

Tenant schemas are never created by hand. `bin/console tenant:provision <name>`
(`App\Tenancy\Presentation\Command\ProvisionTenantCommand`) does it in one step:

1. Validates `<name>` against a strict whitelist — lowercase alphanumeric segments
   separated by single hyphens (e.g. `acme`, `acme-bv`), max 55 characters. This is
   the only thing standing between a free-text name and SQL-schema injection, since
   the name ends up in raw `CREATE SCHEMA`/`SET search_path` identifiers.
2. Derives the Postgres schema name from it (`tenant_<name>`, hyphens → underscores)
   and fails cleanly, before creating anything, if that subdomain, that schema name,
   or a raw Postgres schema with that name already exists.
3. Creates the schema and runs the **tenant** migration set on it (see below).
4. Registers the tenant (`subdomain` → `schema_name`) in the public `tenants` table.

If step 3 or 4 fails after the schema was created, the schema is dropped again before
the error propagates — a failed run never leaves a half-migrated schema behind.

```bash
make console args="tenant:provision acme-bv"
```

Migrations are split into two independent sets:

- **Public migrations** (`api/migrations/`, namespace `DoctrineMigrations`) — the
  `public` schema, e.g. the `tenants` table itself. Run the usual way:
  `make console args="doctrine:migrations:migrate"`.
- **Tenant migrations** (`api/migrations-tenant/`, namespace `App\Migrations\Tenant`)
  — run once per tenant schema by `App\Tenancy\Infrastructure\TenantSchemaMigrator`,
  never by `doctrine:migrations:migrate`. Each tenant schema tracks its own applied
  versions in a `doctrine_migration_versions` table living inside that schema, so
  the same tenant migration can run once per tenant independently.

To roll a tenant-schema change out across every existing tenant, run:

```bash
make console args="tenant:migrate --all"
```

This migrates every tenant registered in `tenants` to the latest tenant migration
version. One tenant failing to migrate is reported but does not stop the others.

### Testing multiple subdomains locally

Every `<tenant>.kozijnr.localhost` (or `<tenant>.koz-<n>.kozijnr.localhost` in
a worktree) is routed by the shared proxy without any registration — see
"Local domains via nginx". With the stack running (`make up`), provision a
tenant and hit it, either in the browser or with curl (which needs `--resolve`):

```bash
make console args="tenant:provision acme"

R="--resolve api.kozijnr.localhost:80:127.0.0.1"
curl $R -H 'Origin: http://acme.kozijnr.localhost' http://api.kozijnr.localhost/api/health            # 200, search_path = tenant_acme, public
curl $R http://api.kozijnr.localhost/api/health                                                      # 200, search_path = public (no tenant)
curl $R -H 'Origin: http://unknown-tenant.kozijnr.localhost' http://api.kozijnr.localhost/api/health  # 404, no fallback to any schema
```

### Super admin (KOZ-8)

A super admin is a tenant-independent account, stored only in the public schema
(`users` table — a tenant schema never has this table at all), that can list
and create tenants. Create one with:

```bash
make console args="super-admin:create admin@kozijnr.nl"
```

(prompts for a password if omitted from the arguments), then log in at
http://admin.kozijnr.localhost/login. The admin API lives under `/api/admin/*`
on `api.<base>` and is reachable *only* in the admin context — i.e. with
`Origin: http://admin.<base>` (what the browser on the admin frontend sends);
it 404s for tenant origins, for unrecognized origins and without any origin.
See `App\Tenancy\Infrastructure\AdminRouteGuardListener`. From curl:

```bash
R="--resolve api.kozijnr.localhost:80:127.0.0.1"
O="Origin: http://admin.kozijnr.localhost"

curl $R -i -c cookies.txt -X POST -H "$O" http://api.kozijnr.localhost/api/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@kozijnr.nl","password":"<password>"}'

curl $R -b cookies.txt -H "$O" http://api.kozijnr.localhost/api/admin/tenants            # list: subdomain + createdAt

curl $R -b cookies.txt -H "$O" -X POST http://api.kozijnr.localhost/api/admin/tenants \
  -H 'Content-Type: application/json' \
  -d '{"name":"acme"}'                                                                     # create, via KOZ-7's tenant:provision
```

For a worktree, substitute `koz-<n>.kozijnr.localhost` for `kozijnr.localhost`.

## Versions

- PHP: **8.5** (pinned in `docker/backend/Dockerfile`, image `php:8.5-cli-alpine`)
- Node: **24** (pinned in `docker/frontend/Dockerfile`, image `node:24-alpine` — latest stable LTS as of this writing)
- PostgreSQL: **16** (`postgres:16-alpine`)

These versions are pinned explicitly and won't silently move forward with new releases —
bump them deliberately in the relevant Dockerfile when needed.

## Out of scope

This setup covers the local development environment only. Production deployment and
CI/CD configuration are handled separately.

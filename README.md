# Kozijnr

Monorepo containing:

- `api/` — backend, Symfony (PHP 8.5)
- `web/` — frontend, Next.js (Node 24, latest stable LTS)
- `docker/` — Dockerfiles for the local dev environment
- `docker-compose.yml` — orchestrates backend, frontend and database for local development
- `Makefile` — thin wrappers around the `docker compose` commands below

## Requirements

- Docker Desktop (or an equivalent Docker Engine + Compose v2 install)

Nothing else needs to be installed on the host — PHP, Composer, Node and npm all run inside the containers.

## Quick start

```bash
make up
```

This builds the images (first run only) and starts everything in the background:

- Frontend (Next.js): http://localhost:3000
- Backend (Symfony): http://localhost:8000 — health check at http://localhost:8000/api/health
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
| `make up` | Start the stack in the background (builds images if needed) |
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

Each git worktree (typically one per KOZ ticket) can run its own isolated
copy of the stack — different container names, different volumes, different
ports — so multiple tickets can be developed and tested locally at the same
time without conflicts.

The convention is based on the KOZ issue number `<n>`:

| Service | Port formula | Example for KOZ-12 |
| --- | --- | --- |
| Frontend | `3000 + <n>` | `http://localhost:3012` |
| Backend | `8000 + <n>` | `http://localhost:8012` |
| Database | `5432 + <n>` | `localhost:5444` |

Container and volume names are namespaced by setting `COMPOSE_PROJECT_NAME=koz-<n>`
(instead of the default `kozijnr`), so e.g. KOZ-12's backend container becomes
`koz-12-backend-1` and its database volume `koz-12_database_data` — distinct
from any other worktree's containers/volumes running at the same time.

This is set up automatically: `make up` derives the issue number `<n>` from
the current branch name (expected to follow the `koz-<n>` convention, e.g.
branch `koz-12`) and generates the worktree's `.env` on the fly if one
doesn't exist yet — via `scripts/setup-worktree-env.sh <n>` — before starting
the stack. So in a fresh `koz-<n>` worktree, a single

```bash
make up
```

is enough; there's no separate setup step to remember. If `.env` already
exists (e.g. from a previous run, or a manually created one), it is left
untouched — `make up` never overwrites it.

If the current branch doesn't follow the `koz-<n>` convention (e.g. `main`,
or a differently named branch) and no `.env` exists yet, `make up` fails
with an explanatory error instead of silently falling back to the default
ports — that fallback would be confusing to debug when several worktrees
are meant to run side by side. In that case, either checkout a `koz-<n>`
branch, or generate/override `.env` explicitly:

```bash
make worktree-env n=12   # for KOZ-12 — (re)generates .env regardless of branch name
```

or directly:

```bash
scripts/setup-worktree-env.sh 12
```

This writes a `.env` file (gitignored, one per worktree — see `.env.example`
for the shape) with `COMPOSE_PROJECT_NAME`, `FRONTEND_PORT`, `BACKEND_PORT`,
`DATABASE_PORT` and `NEXT_PUBLIC_API_URL` set for that issue number. Use it
whenever you need to (re)generate `.env` with a specific issue number,
regardless of what the current branch is named — e.g. to override the
number `make up` would have derived automatically, or to regenerate after
manually editing `.env`.
`docker-compose.yml` reads these variables with the plain defaults
(3000/8000/5432, project name `kozijnr`) as fallback, so the regular
non-worktree workflow described above is unaffected if `.env` is absent and
you're not on a `koz-<n>` branch (e.g. when running `docker compose` outside
of `make up` directly).

Because `NEXT_PUBLIC_API_URL` is derived from the same `.env`, the frontend
in each worktree automatically talks to its own worktree's backend port —
no manual wiring needed.

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
   relative to the `APP_BASE_DOMAIN` env var (default `localhost`; set to the real
   apex domain, e.g. `kozijnr.nl`, in production).
   - No subdomain, or a request to the base domain itself → stays on the `public`
     schema. No tenant lookup happens.
   - A subdomain present → looked up in the `tenants` table (public schema, see the
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

`*.localhost` (e.g. `tenant-a.localhost`, `tenant-b.localhost`) resolves to `127.0.0.1`
on most systems out of the box (macOS, and most modern Linux/Windows setups) — no
`/etc/hosts` edits needed. If that's not the case on your machine, add entries like:

```
127.0.0.1 tenant-a.localhost
127.0.0.1 tenant-b.localhost
```

to `/etc/hosts` instead.

With the stack running (`make up`) and the `APP_BASE_DOMAIN` left at its default
(`localhost`), provision a tenant with `tenant:provision` for a manual smoke test —
e.g. for KOZ-6's worktree (backend on port 8006, database on port 5438):

```bash
make console args="tenant:provision acme"

curl http://acme.localhost:8006/api/health        # 200, served with search_path = tenant_acme, public
curl http://localhost:8006/api/health             # 200, served with search_path = public (no tenant)
curl http://unknown-tenant.localhost:8006/api/health  # 404, no fallback to any schema
```

Adjust the port to whichever worktree you're in (see "Per-worktree test environments"
above).

## Versions

- PHP: **8.5** (pinned in `docker/backend/Dockerfile`, image `php:8.5-cli-alpine`)
- Node: **24** (pinned in `docker/frontend/Dockerfile`, image `node:24-alpine` — latest stable LTS as of this writing)
- PostgreSQL: **16** (`postgres:16-alpine`)

These versions are pinned explicitly and won't silently move forward with new releases —
bump them deliberately in the relevant Dockerfile when needed.

## Out of scope

This setup covers the local development environment only. Production deployment and
CI/CD configuration are handled separately.

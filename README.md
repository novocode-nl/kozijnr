# Kozijnr

Monorepo containing:

- `api/` — backend, Symfony (PHP 8.5)
- `web/` — frontend, Next.js (Node 24, latest stable LTS)
- `docker/` — Dockerfiles for the local dev environment
- `docker-compose.yml` — orchestrates backend, frontend and database for local development
- `Makefile` — thin wrappers around the `docker compose` commands below

## Requirements

- Docker Desktop (or an equivalent Docker Engine + Compose v2 install)
- [Laravel Valet](https://laravel.com/docs/valet) (macOS only) — **optional**, but recommended: gives every checkout/worktree nice `*.test` domains (`api.kozijnr.test`, `admin.kozijnr.test`, `<tenant>.kozijnr.test`) instead of having to remember a port number. See "Local domains via Laravel Valet" below. Everything in this README also works without it, using `localhost:<port>` directly.

Nothing else needs to be installed on the host — PHP, Composer, Node and npm all run inside the containers. Valet itself is the one exception: it's a thin host-level DNS + reverse-proxy layer sitting *in front of* Docker, not a replacement for it — Docker still runs the actual backend/frontend/database containers exactly as described below.

## Quick start

```bash
make up
```

This builds the images (first run only) and starts everything in the background:

- Frontend (Next.js): http://localhost:3000
- Backend (Symfony): http://localhost:8000 — health check at http://localhost:8000/api/health, or (with Valet set up — see below) http://api.kozijnr.test/api/health
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
| Backend | `8000 + <n>` | `http://localhost:8012`, or `api.kozijnr-koz-12.test` via Valet |
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
`DATABASE_PORT`, `NEXT_PUBLIC_API_URL` and `APP_BASE_DOMAIN` set for that issue
number, and (KOZ-12) registers that worktree's `api.`/`admin.` Valet proxies if
the `valet` CLI is available — see "Local domains via Laravel Valet" above. Use it
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

## Local domains via Laravel Valet (KOZ-12)

The port-based URLs above (`localhost:8000`, `localhost:8012`, ...) always
work and remain the fallback, but they're easy to lose track of once
several worktrees are running side by side. [Laravel Valet](https://laravel.com/docs/valet)
adds a nicer, per-worktree `*.test` domain scheme on top — Valet is **only**
a local DNS + reverse-proxy layer in front of the same Docker containers;
it never replaces Docker, and it's entirely optional.

### One-time setup (per developer machine, macOS only)

```bash
brew install php composer   # if not already installed — Valet itself needs a host PHP, unrelated to the Dockerized backend
composer global require laravel/valet
valet install                # sets up Valet's DnsMasq + Nginx daemons and the .test TLD
```

Then, **once**, from the repo root of your main `kozijnr` checkout:

```bash
valet park
```

`valet park` registers the *directory* (not a specific project) — every
subdirectory of the parked directory automatically gets proxy support, so
this is not repeated per worktree. If your worktrees live in a sibling
directory rather than under the main checkout (e.g. `.worktrees/koz-12`
inside the repo itself, per this repo's convention — see "Per-worktree test
environments" above), a single `valet park` on the repo root already covers
them, since they're nested underneath it.

### Domain scheme

| Checkout | api (base domain, no tenant) | admin | tenant |
| --- | --- | --- | --- |
| Main checkout | `api.kozijnr.test` | `admin.kozijnr.test` | `<tenant>.kozijnr.test` |
| Worktree `koz-<n>` | `api.kozijnr-koz-<n>.test` | `admin.kozijnr-koz-<n>.test` | `<tenant>.kozijnr-koz-<n>.test` |

`api` is a second reserved subdomain name (`App\Tenancy\Domain\Subdomain::RESERVED_API`,
alongside the existing `admin` — see "Multi-tenancy" below) standing in for
what used to be a bare `localhost:<port>` request with no subdomain at all:
under Valet there's always *some* host, so `api.<base>` is the base-domain
entry point — no tenant, no admin, same as the old no-subdomain request.
`api`/`admin` can never be provisioned as an actual tenant name either
(`TenantName` rejects both).

### How the proxies get registered

- **Static (`api.`/`admin.`) proxies**, both for the main checkout and for
  each worktree, are `valet proxy <domain> http://127.0.0.1:<port>`
  registrations — Valet's `proxy` driver doesn't support wildcard
  subdomains, so `admin`/`api` need their own explicit entry rather than
  matching a `*.kozijnr.test` pattern. For the main checkout, register these
  by hand once:

  ```bash
  valet proxy api.kozijnr.test http://127.0.0.1:8000
  valet proxy admin.kozijnr.test http://127.0.0.1:8000
  ```

  For a worktree, `scripts/setup-worktree-env.sh <n>` (the same script that
  generates the worktree's `.env` — see "Per-worktree test environments"
  above) registers these two automatically, in addition to the ports, if the
  `valet` CLI is available; it prints what it registered (or a note to do it
  manually if Valet isn't installed).

- **Dynamic tenant proxies** (`<tenant>.kozijnr(-koz-<n>)?.test`) are
  *queued* automatically the moment a tenant is created, whichever path
  created it (the admin API, `tenant:provision`, ...), then registered by
  running **`make valet-sync`** once. This is a two-step, deliberately
  on-demand process rather than one automatic step — see "Why not fully
  automatic?" just below for why.

  `App\Tenancy\Infrastructure\Valet\TenantValetProxyListener` is a
  dev-environment-only Doctrine `postPersist` listener (wired only in
  `api/config/services_dev.yaml`, never in `test`/`prod`) that fires right
  after a tenant is persisted. It never calls `valet` itself — the backend
  runs inside a Docker container that has no `valet` binary and no access
  to the host's Valet installation at all, so no amount of retrying from in
  there could ever make a direct call work. Instead it *queues* the
  request: `App\Tenancy\Infrastructure\Valet\FilesystemValetProxyQueue`
  writes a small JSON file (domain + target) to
  `api/var/valet-proxy-queue/pending/`, a path docker-compose.yml already
  bind-mounts onto the host as part of `./api:/app` — so the file is
  immediately visible on the host filesystem too, no extra Docker volume
  needed. It's best-effort: if the queue directory can't be written to, a
  warning is logged and tenant creation still succeeds — this is dev
  convenience, never a hard dependency.

  ```bash
  make console args="tenant:provision acme"   # queues acme.kozijnr.test
  make valet-sync                              # actually registers it with Valet
  ```

  `make valet-sync` (`scripts/valet-sync.sh`) runs on the host: it reads
  every pending request, calls `valet proxy <domain> <target>` for it, and
  moves the request to `api/var/valet-proxy-queue/processed/` on success
  (kept for troubleshooting) — or leaves it pending, to retry on the next
  run, if `valet proxy` fails. Safe to run any time, repeatedly, with
  nothing pending (a no-op), and a no-op if Valet isn't installed at all.

  #### Why not fully automatic?

  Making this fully automatic would need something living on the host that
  the container can reach — e.g. a small daemon listening on
  `host.docker.internal` that the container calls into, which then runs
  `valet proxy` for real. That was considered and rejected: it would need
  to be started before it's useful, kept running in the background, and
  managed as its own long-lived process (e.g. a `launchd` service) for
  something only needed right after creating a tenant — a heavier,
  higher-maintenance piece of infrastructure than the problem warrants.
  Other bridging options (SSH from the container into the host, proxying
  the Docker socket) have the same shape: a standing service and an open
  channel into the host, for an operation that happens a handful of times
  per dev session.

  The file-based queue plus an on-demand `make valet-sync` needs no
  background process, no listening port and no separate lifecycle to
  manage — only filesystem I/O, run whenever it's convenient. If you want
  near-real-time syncing without remembering to run `make valet-sync` by
  hand, start an optional watcher in its own terminal (no code change
  needed, and entirely optional — the file queue is what guarantees
  correctness, this is just comfort on top):

  ```bash
  brew install fswatch   # one-time
  fswatch -o api/var/valet-proxy-queue/pending | xargs -n1 -I{} make valet-sync
  ```

### Verifying it works

With the stack running (`make up`) and (for the main checkout) the static
proxies registered as above:

```bash
curl http://api.kozijnr.test/api/health          # 200, public schema, no tenant
make console args="tenant:provision acme"        # queues acme.kozijnr.test (see above)
make valet-sync                                   # registers it with Valet for real
curl http://acme.kozijnr.test/api/health          # 200, search_path = tenant_acme, public
curl http://admin.kozijnr.test/api/health         # 200, public schema, reserved admin domain
```

For a worktree, substitute `kozijnr-koz-<n>.test` for `kozijnr.test` — see
the domain scheme table above.

### Cleaning up a worktree's proxies

`scripts/teardown-worktree-valet.sh <n>` (or `make worktree-valet-teardown n=<n>`)
removes a worktree's `api.`/`admin.` proxies and every tenant proxy
registered under its domain. This runs automatically as part of worktree
cleanup once a ticket is merged — see the project's `asana-user-review`
workflow — so finished worktrees don't leave dead proxy registrations
behind. Safe to run manually too, and a no-op if Valet isn't installed.

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
   - `admin` subdomain (e.g. `admin.localhost`, `admin.kozijnr.nl` in production) →
     recognized as the reserved, tenant-independent super-admin domain (see "Super
     admin" below) and stays on the `public` schema. Never looked up in `tenants`,
     and can never itself be provisioned as a tenant name — `TenantName` rejects
     `admin` outright.
   - `api` subdomain (KOZ-12; e.g. `api.kozijnr.test` locally via Valet, `api.kozijnr.nl`
     in production) → also reserved, also stays on the `public` schema, also rejected
     by `TenantName` as a tenant name. Functionally the replacement for what used to
     be a bare no-subdomain request: under Valet's proxy layer there's always *some*
     host, so `api.<base>` is the base-domain entry point rather than a truly
     subdomain-less request. See "Local domains via Laravel Valet" above.
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

The recommended way is Valet (see "Local domains via Laravel Valet" above) — nice
`*.kozijnr(-koz-<n>)?.test` domains, no port to remember, tenant proxies registered
automatically on creation.

Without Valet, the same thing works directly against the port-based URLs:
`*.localhost` (e.g. `tenant-a.localhost`, `tenant-b.localhost`) resolves to `127.0.0.1`
on most systems out of the box (macOS, and most modern Linux/Windows setups) — no
`/etc/hosts` edits needed. If that's not the case on your machine, add entries like:

```
127.0.0.1 tenant-a.localhost
127.0.0.1 tenant-b.localhost
```

to `/etc/hosts` instead. This requires overriding `APP_BASE_DOMAIN` back to
`localhost` (its default is `kozijnr.test`, for Valet — see `api/.env`), e.g. via
`api/.env.local`.

With the stack running (`make up`), provision a tenant with `tenant:provision` for a
manual smoke test — e.g. for KOZ-6's worktree (backend on port 8006, database on port
5438), with `APP_BASE_DOMAIN=localhost`:

```bash
make console args="tenant:provision acme"

curl http://acme.localhost:8006/api/health        # 200, served with search_path = tenant_acme, public
curl http://localhost:8006/api/health             # 200, served with search_path = public (no tenant)
curl http://unknown-tenant.localhost:8006/api/health  # 404, no fallback to any schema
```

Adjust the port to whichever worktree you're in (see "Per-worktree test environments"
above).

### Super admin (KOZ-8)

A super admin is a tenant-independent account, stored only in the public schema
(`users` table — a tenant schema never has this table at all), that can list
and create tenants. Create one with:

```bash
make console args="super-admin:create admin@kozijnr.nl"
```

(prompts for a password if omitted from the arguments), then, on the reserved
`admin` subdomain (`admin.kozijnr.nl` in production; locally, `admin.kozijnr.test`
via Valet or `admin.localhost:<port>` without it — see "Local domains via Laravel
Valet" / "Testing multiple subdomains locally" above) — and *only* there.
`/api/admin/*` 404s everywhere else: on any tenant subdomain, on an unrecognized
subdomain, and on the bare main domain too, since `admin.kozijnr.nl` is the one
place admin business happens, not a fallback available from the apex domain. See
`App\Tenancy\Infrastructure\AdminRouteGuardListener`:

```bash
curl -i -c cookies.txt -X POST http://admin.kozijnr.test/api/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@kozijnr.nl","password":"<password>"}'

curl -b cookies.txt http://admin.kozijnr.test/api/admin/tenants                 # list: subdomain + createdAt

curl -b cookies.txt -X POST http://admin.kozijnr.test/api/admin/tenants \
  -H 'Content-Type: application/json' \
  -d '{"name":"acme"}'                                                          # create, via KOZ-7's tenant:provision
                                                                                  # (also auto-registers acme.kozijnr.test via Valet, see KOZ-12)
```

For a worktree, substitute `admin.kozijnr-koz-<n>.test`. Without Valet, substitute
`admin.localhost:<port>` for whichever worktree you're in instead (and set
`APP_BASE_DOMAIN=localhost` — see "Testing multiple subdomains locally" above).

## Versions

- PHP: **8.5** (pinned in `docker/backend/Dockerfile`, image `php:8.5-cli-alpine`)
- Node: **24** (pinned in `docker/frontend/Dockerfile`, image `node:24-alpine` — latest stable LTS as of this writing)
- PostgreSQL: **16** (`postgres:16-alpine`)

These versions are pinned explicitly and won't silently move forward with new releases —
bump them deliberately in the relevant Dockerfile when needed.

## Out of scope

This setup covers the local development environment only. Production deployment and
CI/CD configuration are handled separately.

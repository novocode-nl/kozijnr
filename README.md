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

## Versions

- PHP: **8.5** (pinned in `docker/backend/Dockerfile`, image `php:8.5-cli-alpine`)
- Node: **24** (pinned in `docker/frontend/Dockerfile`, image `node:24-alpine` — latest stable LTS as of this writing)
- PostgreSQL: **16** (`postgres:16-alpine`)

These versions are pinned explicitly and won't silently move forward with new releases —
bump them deliberately in the relevant Dockerfile when needed.

## Out of scope

This setup covers the local development environment only. Production deployment and
CI/CD configuration are handled separately.

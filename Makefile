# Thin wrappers around `docker compose` for the local dev environment.
# No logic beyond that lives here — see docker-compose.yml for the real setup.

COMPOSE := docker compose
SHELL := /bin/bash

.PHONY: up down build rebuild restart logs ps sh-backend sh-frontend \
        composer console npm worktree-env ensure-env

## Ensure a per-worktree .env exists before the stack starts. Runs
## automatically as a prerequisite of `up` so a single `make up` suffices in
## a fresh worktree — see "Per-worktree test environments" in README.md.
##
## - If .env already exists, it is left untouched (no overwrite), so manual
##   edits or a deliberately different issue number survive.
## - Otherwise the issue number is derived from the current branch name,
##   which is expected to follow the `koz-<n>` convention, and
##   scripts/setup-worktree-env.sh <n> is invoked to generate .env.
## - If the branch name doesn't match `koz-<n>` (e.g. `main`), this fails
##   loudly with an explanation rather than silently falling back to the
##   default ports (3000/8000/5432) — that fallback would be confusing to
##   debug when several worktrees are meant to run side by side.
ensure-env:
	@if [ -f .env ]; then \
		echo ".env already exists — leaving it as-is."; \
	else \
		BRANCH="$$(git rev-parse --abbrev-ref HEAD)"; \
		if [[ "$$BRANCH" =~ ^koz-([1-9][0-9]*)$$ ]]; then \
			N="$${BASH_REMATCH[1]}"; \
			echo "No .env found — deriving issue number $$N from branch '$$BRANCH'."; \
			./scripts/setup-worktree-env.sh "$$N"; \
		else \
			echo "Error: no .env found, and current branch '$$BRANCH' does not match the 'koz-<n>' naming convention," >&2; \
			echo "so the KOZ issue number could not be derived automatically." >&2; \
			echo "Fix: checkout a branch named 'koz-<n>', or run 'make worktree-env n=<n>' (or create .env manually) first." >&2; \
			exit 1; \
		fi; \
	fi

## Start the full stack in the background. Generates .env automatically
## (see `ensure-env`) if it doesn't exist yet.
up: ensure-env
	$(COMPOSE) up -d

## Stop and remove the stack's containers.
down:
	$(COMPOSE) down

## Build (or rebuild) all images.
build:
	$(COMPOSE) build

## Rebuild images from scratch and restart the stack.
rebuild:
	$(COMPOSE) up -d --build

## Restart all services.
restart:
	$(COMPOSE) restart

## Tail logs for all services.
logs:
	$(COMPOSE) logs -f

## Show status of the stack's containers.
ps:
	$(COMPOSE) ps

## Open a shell in the backend container.
sh-backend:
	$(COMPOSE) exec backend sh

## Open a shell in the frontend container.
sh-frontend:
	$(COMPOSE) exec frontend sh

## Run composer inside the backend container, e.g. `make composer args="require foo/bar"`.
composer:
	$(COMPOSE) exec backend composer $(args)

## Run bin/console inside the backend container, e.g. `make console args="cache:clear"`.
console:
	$(COMPOSE) exec backend php bin/console $(args)

## Run npm inside the frontend container, e.g. `make npm args="run lint"`.
npm:
	$(COMPOSE) exec frontend npm $(args)

## Generate a per-worktree .env with ports/names derived from a KOZ issue
## number, e.g. `make worktree-env n=12` for KOZ-12. See README.md.
worktree-env:
	./scripts/setup-worktree-env.sh $(n)

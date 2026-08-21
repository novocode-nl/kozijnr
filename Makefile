# Thin wrappers around `docker compose` for the local dev environment.
# No logic beyond that lives here — see docker-compose.yml for the real setup.

COMPOSE := docker compose
SHELL := /bin/bash

.PHONY: up down build rebuild restart logs ps sh-backend sh-frontend \
        composer console npm test-backend worktree-env ensure-env \
        proxy-up proxy-down proxy-logs

PROXY_COMPOSE := docker compose -f docker/proxy/docker-compose.yml
PROXY_NETWORK := kozijnr-proxy

## Ensure a .env exists before the stack starts. Runs automatically as a
## prerequisite of `up` so a single `make up` suffices in a fresh checkout or
## worktree — see "Per-worktree test environments" in README.md.
##
## - If .env already exists, it is left untouched (no overwrite).
## - On branch `main`, the main checkout's .env is generated
##   (project "kozijnr", base domain kozijnr.localhost).
## - On a `koz-<n>` branch, the issue number is derived from the branch name
##   and an isolated per-worktree .env is generated (project "koz-<n>",
##   base domain koz-<n>.kozijnr.localhost).
## - Any other branch name fails loudly rather than guessing — when several
##   stacks run side by side a silent default would be confusing to debug.
ensure-env:
	@if [ -f .env ]; then \
		echo ".env already exists — leaving it as-is."; \
	else \
		BRANCH="$$(git rev-parse --abbrev-ref HEAD)"; \
		if [ "$$BRANCH" = "main" ]; then \
			echo "No .env found — generating the main checkout's .env."; \
			./scripts/setup-worktree-env.sh main; \
		elif [[ "$$BRANCH" =~ ^koz-([1-9][0-9]*)$$ ]]; then \
			N="$${BASH_REMATCH[1]}"; \
			echo "No .env found — deriving issue number $$N from branch '$$BRANCH'."; \
			./scripts/setup-worktree-env.sh "$$N"; \
		else \
			echo "Error: no .env found, and current branch '$$BRANCH' is neither 'main' nor 'koz-<n>'," >&2; \
			echo "so the environment could not be derived automatically." >&2; \
			echo "Fix: run 'make worktree-env n=<n>' (or 'make worktree-env n=main'), or create .env by hand." >&2; \
			exit 1; \
		fi; \
	fi

## Start the shared nginx reverse proxy (idempotent; creates the external
## docker network it needs). One proxy serves the main checkout AND every
## worktree, routing api|admin|<tenant>[.koz-<n>].kozijnr.localhost by
## hostname — see docker/proxy and README.md "Local domains via nginx".
proxy-up:
	@docker network inspect $(PROXY_NETWORK) >/dev/null 2>&1 || docker network create $(PROXY_NETWORK)
	$(PROXY_COMPOSE) up -d

## Stop the shared proxy (all stacks become unreachable over HTTP until
## `make proxy-up` again; the stacks themselves keep running).
proxy-down:
	$(PROXY_COMPOSE) down

## Tail the shared proxy's logs.
proxy-logs:
	$(PROXY_COMPOSE) logs -f

## Start the full stack in the background. Generates .env automatically
## (see `ensure-env`) if it doesn't exist yet, and makes sure the shared
## proxy is running.
up: ensure-env proxy-up
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

## Run the backend PHPUnit suite inside the backend container, e.g.
## `make test-backend args="tests/Tenancy"`. Forces APP_ENV=test — the
## container's default APP_ENV=dev (set in docker-compose.yml for the `php
## -S` dev server) otherwise wins over phpunit.dist.xml's own APP_ENV
## override, since a real container env var takes precedence over it.
test-backend:
	$(COMPOSE) exec -e APP_ENV=test backend php bin/phpunit $(args)

## (Re)generate .env for a KOZ issue number (`make worktree-env n=12`) or the
## main checkout (`make worktree-env n=main`). See README.md.
worktree-env:
	./scripts/setup-worktree-env.sh $(n)

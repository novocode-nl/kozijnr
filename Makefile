# Thin wrappers around `docker compose` for the local dev environment.
# No logic beyond that lives here — see docker-compose.yml for the real setup.

COMPOSE := docker compose

.PHONY: up down build rebuild restart logs ps sh-backend sh-frontend \
        composer console npm worktree-env

## Start the full stack in the background.
up:
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

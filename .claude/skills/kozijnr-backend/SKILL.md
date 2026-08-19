---
name: kozijnr-backend
description: Use when implementing the backend (Symfony/PHP) part of a Kozijnr ticket — inside the per-ticket subagent that asana-worker spawns, or any time backend code (API, domain logic, CQRS handlers, Messenger events) needs to be written for this project.
---

# Kozijnr Backend

## Overview

Kozijnr's backend is Symfony, built as DDD + Hexagonal architecture with CQRS and Messenger-driven events, under strict TDD. This skill is self-contained — it does not assume any external architecture-knowledge plugin is installed or enabled, so read it in full rather than expecting it to hand off to something else.

**REQUIRED BACKGROUND:** `superpowers:test-driven-development` — no implementation code before a failing test exists. This applies here without exception.

**Taal:** code, identifiers en comments in het Engels (standaard in dit type codebase); communicatie naar de gebruiker (samenvattingen, Asana-comments) in het Nederlands.

## When to Use

- The per-ticket subagent from `asana-worker` determines the ticket touches backend/API/domain logic
- Any direct request to add a use case, endpoint, entity, bounded context, domain event, or fix a backend bug

Not for frontend work — see `kozijnr-frontend`. A ticket can need both; run them as the ticket's scope requires, not necessarily in the same pass.

## Architecture: DDD + Hexagonal

**Layout per bounded context**, under `/api/src/<Context>/`:

```
<Context>/
  Domain/          entities, value objects, aggregates, domain events, repository interfaces
  Application/      commands, queries, their handlers, application services
  Infrastructure/    repository implementations, Messenger adapters, external service adapters
  Presentation/       controllers / API endpoints
```

Plus `/api/src/Shared/` for cross-cutting bus wiring and truly shared kernel concepts. No bounded contexts exist yet in this repo — the first backend ticket establishes the first `<Context>` folder; grep `/api/src` before inventing a second context for a concept that might already exist.

**Hexagonal rule:** `Domain/` depends on nothing outside itself. `Application/` depends only on `Domain/` (repository *interfaces*, not implementations). `Infrastructure/` implements the `Domain/` interfaces (ports & adapters) and is the only layer allowed to know about Doctrine, Symfony Messenger transports, HTTP clients, etc. `Presentation/` depends only on `Application/` — a controller dispatches a command/query and maps the result to a response; it never contains business logic.

**DDD building blocks:**
- **Entity** — has identity, mutable state, lives inside an aggregate.
- **Value Object** — immutable, equality by value, no identity. Validates its own invariants in its constructor.
- **Aggregate** — one root entity per aggregate, is the only thing referenced from outside, enforces all invariants for the entities/VOs it contains, raises domain events on state changes.
- **Repository** — interface lives in `Domain/`, one per aggregate root, implementation in `Infrastructure/`. Never expose a query builder or ORM-specific type through the interface.

## CQRS

- One **Command** + one **CommandHandler** per write use case. Commands are plain data (name them as imperatives: `PlaceOrder`, not `OrderCommand`). Handlers return `void` or, at most, the new aggregate's ID — never an entity.
- One **Query** + one **QueryHandler** per read use case. Query handlers **never** cause side effects and typically bypass the domain model for a direct read model / projection.
- Three separate Symfony Messenger buses, each configured with its own routing in `messenger.yaml`: `command.bus`, `query.bus`, `event.bus`. Never dispatch a query on the command bus or vice versa.

## Controllers: one action per class (SRP)

Every controller class has exactly **one** public action and exactly **one** route. This is toetsbaar, not a style preference — check any controller against this list:

- [ ] The class declares exactly one `#[Route(...)]` attribute, on a single public method named `__invoke`. Two routes (even the same path with different HTTP methods, e.g. `GET`/`POST /api/x`) means two controller classes, not two methods on one class.
- [ ] The class name describes the one thing it does, as a verb + subject, e.g. `ListTenantsController`, `CreateTenantController` — never a resource-only name like `TenantController` or `TenantAdminController` that invites a second action to be bolted on later.
- [ ] The method body only does HTTP translation: parse the request into primitives, call exactly one `Application/` command or query (constructor-injected), map the result or a caught domain exception to a `Response`. No branching on business rules, no loops that compute something domain-shaped, no calls into more than one Application-layer use case.
- [ ] Authorization (`#[IsGranted(...)]`) is a class-level attribute (or applies to the single action) with the permission specific to that one action — never a shared permission check gating two different actions bolted onto the same class.
- [ ] Response-shaping helpers (turning a DTO into an array) belong on the Application-layer DTO itself (e.g. a `toArray()` method on the read model) if more than one controller needs the same shape, not copy-pasted per controller and not reason enough to keep two actions in one class "so they can share the helper".

If a change looks like it wants to add a second method to an existing controller, that is the signal to create a new controller class instead — one file, one route, one job. This applies to every context's controllers, wherever they currently live in that context's folder tree (this repo's contexts currently keep them under `Infrastructure/Controller/`, ahead of a later cleanup toward the `Presentation/` layout described above — don't block a controller split on that unrelated inconsistency).

## Domain events & Messenger

- Aggregates raise domain events (plain data objects) when meaningful state changes happen; the event isn't dispatched until after the transaction that produced it commits.
- Dispatch domain events on `event.bus`. Handlers that must run in the same request stay synchronous; handlers that can run later go through an async Messenger transport.
- For anything where losing an event would be a real problem (payment, order confirmation, etc.), use the outbox pattern: persist the event in the same DB transaction as the state change, in an `outbox` table, and have a separate worker publish + delete it — don't rely on "dispatch after commit" alone for those cases.

## TDD workflow for a ticket

1. Write a failing unit test for the `Domain/` logic (aggregate/VO invariant) first.
2. Implement the minimal `Domain/` code to pass it.
3. Write a failing test for the `Application/` handler (mocking the repository interface).
4. Implement the handler.
5. Write an integration test that wires the real repository + bus + controller together end to end.
6. Only then wire up `Presentation/`.

## Definition of Done gate (no external check-skills available — verify these yourself)

- [ ] All new business logic lives in `Domain/`, not in a handler, controller, or repository implementation.
- [ ] Command handlers return void/ID only; query handlers have zero side effects.
- [ ] Command bus, query bus, and event bus are not mixed.
- [ ] Every new public behavior has a test that was written before the implementation (TDD, not tests-after).
- [ ] Every controller has exactly one route/action — see "Controllers: one action per class (SRP)" above.
- [ ] The ticket's own "Definition of Done" bullets are individually satisfied — treat them as the acceptance test, not a suggestion.

## Common Mistakes

- **Business logic in a controller or command handler** instead of the aggregate — breaks the hexagonal boundary and makes it untestable without the framework.
- **Writing implementation before a failing test** — see REQUIRED BACKGROUND. No exceptions for "simple" CRUD-looking tickets.
- **One Messenger bus for everything** — always split command/query/event buses, even for a small feature.
- **Assuming a bounded-context name or folder** without checking whether one already exists under `/api/src` — grep first, don't guess a second context for the same concept.
- **Dispatching a domain event before its transaction commits** — a rolled-back write must never have already fired an event.
- **Two actions on one controller class** ("it's just list + create, they're related") — split into one controller per route, see "Controllers: one action per class (SRP)".

---
name: kozijnr-frontend
description: Use when implementing the frontend (Next.js/React) part of a Kozijnr ticket — inside the per-ticket subagent that asana-worker spawns, or any time UI, forms, or client-facing code needs to be written for this project.
---

# Kozijnr Frontend

## Overview

Kozijnr's frontend is Next.js (App Router) with shadcn/ui, Tailwind CSS, and Zod for validation. This skill is self-contained — it does not assume a `shadcn` or `frontend-design` skill/plugin is installed or enabled (they aren't, in this project), so read it in full rather than expecting it to hand off to something else.

**MCP servers configured in `.mcp.json` at the repo root:** `shadcn` (component discovery/search/install), `next-devtools` (live Next.js dev-server introspection — errors, routes, version-accurate docs; only connects once `pnpm dev` is running and the app is on Next.js 16+), `zod-docs` (searches current Zod v4 documentation). Use these tools when available instead of relying on memory — check `/mcp` if unsure they're connected.

**Taal:** code, identifiers en comments in het Engels; communicatie naar de gebruiker (samenvattingen, Asana-comments) in het Nederlands.

## When to Use

- The per-ticket subagent from `asana-worker` determines the ticket touches UI, forms, pages, or client-side logic
- Any direct request to build a page, component, or form for Kozijnr

Not for backend/API work — see `kozijnr-backend`.

## Project structure

- **Monorepo layout:** frontend lives under `/web` at the repo root (sibling to `/api` for the backend, see `kozijnr-backend`). No frontend code exists yet — the first frontend ticket establishes this structure.
- **App Router:** `web/app/` for routes — Server Components by default, `"use client"` only in leaf components that genuinely need interactivity/state/browser APIs. `web/components/ui/` for shadcn-generated primitives, `web/components/` for shared composite components, `web/lib/` for data access and utilities, `web/lib/schemas/` for Zod schemas, `web/hooks/`, `web/types/`. Organize by feature inside these, not one flat dump.

## shadcn/ui components

Use the `shadcn` MCP server first — it can list/search the registry and add components conversationally. Fall back to the CLI only if the MCP tool is unavailable:

- Prefer the MCP's discovery/search tools to find the right component before adding anything.
- Add components via the MCP (or `npx shadcn@latest add <component>` as fallback), don't hand-write them from memory. This generates the component into `web/components/ui/` as owned, editable source — not an npm dependency. Requires a `components.json` in `/web` (created by `npx shadcn@latest init` on the first frontend ticket).
- **Check `web/components/ui/` before adding** — if the component already exists, extend/reuse it rather than adding a duplicate or a competing hand-rolled version.
- Customize via Tailwind classes and the component's existing `cva` (class-variance-authority) variants, not by forking the generated source unless a variant genuinely can't express what's needed.
- Compose complex UI from existing `ui/` primitives in `web/components/`, rather than building new low-level primitives inline in a page.

## Tailwind

- Utility-first in markup; use the `cn()` helper (`clsx` + `tailwind-merge`, the standard shadcn scaffolding utility in `web/lib/utils.ts`) whenever classes are conditional or merged from props.
- Prefer existing design tokens (`tailwind.config` theme values, e.g. `bg-primary`, `text-muted-foreground`) over arbitrary values (`bg-[#123456]`) — arbitrary values mean the token is missing and should be added to the config, not worked around ad hoc.

## Zod & forms

- **Zod is the single source of truth for a shape.** One schema per form/input shape, colocated in `web/lib/schemas/`, used for both the client-side form and the server-side re-validation — derive the TypeScript type with `z.infer<typeof schema>` rather than hand-writing a parallel type.
- **Forms:** react-hook-form + `zodResolver` + shadcn's `Form` components (`web/components/ui/form.tsx`, added via the CLI like any other component). **Never trust client-side validation alone** — every Server Action or API route re-validates the same Zod schema server-side before touching data.
- Use the `zod-docs` MCP to check current Zod v4 API before writing anything non-trivial (refinements, transforms, discriminated unions) — Zod 4's API has moved since earlier versions, don't assume v3-era syntax from memory.

## Testing

Vitest for unit/component tests, Playwright for end-to-end. Write the Vitest test first per the project's TDD discipline (`superpowers:test-driven-development`) before implementing a component or hook with non-trivial logic; purely presentational components don't need a red-green cycle.

## Debugging with next-devtools

Once `/web` has a running dev server (`pnpm dev`, Next.js 16+), use the `next-devtools` MCP instead of guessing: `get_errors` for current build/runtime/type errors, `get_routes`/`get_page_metadata` to confirm actual routing structure before adding a page, `get_server_action_by_id` to trace a Server Action back to its source. Its documentation-gateway tool serves docs matched to the exact installed Next.js version — prefer it over general knowledge when behavior seems version-specific.

## Definition of Done gate

- [ ] No hand-written shadcn component markup where the CLI-generated component already covers it.
- [ ] Every Zod schema has exactly one definition, types derived via `z.infer`, reused client- and server-side.
- [ ] Every Server Action / API route re-validates its input with the same Zod schema server-side.
- [ ] No unnecessary `"use client"` — check each one is actually required.
- [ ] The ticket's own "Definition of Done" bullets are individually satisfied — treat them as the acceptance test, not a suggestion.

## Common Mistakes

- **Writing shadcn component markup from memory** instead of using the `shadcn` MCP (or `npx shadcn@latest add` as fallback) — risks drifting from the actual component API and Tailwind variants.
- **Assuming Zod v3 syntax** — check the `zod-docs` MCP when unsure; Zod 4 changed parts of the API.
- **Duplicating a Zod schema's shape as a hand-written TypeScript interface** — always derive with `z.infer`, one definition per shape.
- **Client-only validation** — a Server Action or API route that skips re-validating the Zod schema server-side is a security gap, not just a convenience shortcut.
- **Marking a component `"use client"` by default** — Server Components are the default in the App Router; only opt into client rendering where interactivity genuinely requires it.

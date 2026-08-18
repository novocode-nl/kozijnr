---
name: asana-worker
description: Use when the user asks to pick up, start, or implement one or more KOZ tickets from the Kozijnr Asana board, e.g. "pak KOZ-7 op", "werk KOZ-3 uit", "implementeer dit ticket", "pak alle openstaande tickets op".
---

# Asana Worker

## Overview

Takes every named (or all "Todo") KOZ ticket through implementation to "Ready for review": looks each one up, moves it to "In progress", and dispatches the actual build work to **one dedicated subagent per ticket**, run in parallel. Each subagent's work is isolated — one ticket's implementation never shares context or state with another's — then each ticket gets its own handoff comment and move to "Ready for review" as its subagent finishes.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs before doing anything else. Cross-reference `superpowers:dispatching-parallel-agents` for how to fan out independent subagents in one batch.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff naar `asana-review`:** de laatste comment die deze skill per ticket plaatst is het overdrachtssignaal. `asana-review` leest de comment-trail (`get_task_stories`) en verwacht die comment om te weten wat er gebouwd is — zie stap 5/6.

## When to Use

- User names one or more specific tickets (`KOZ-N`) and asks to start/implement/pick them up
- User asks broadly to pick up whatever is ready (e.g. "pak alles op wat klaarstaat in Todo")
- Not for reviewing finished work — see `asana-review`
- Not for creating tickets — see `asana-create`

## Workflow

1. **Find the ticket(s).** Call `mcp__asana__get_tasks` with `project=config.project.gid` and `opt_fields=name,gid,completed,notes,memberships.section.name`.
   - Named ticket(s): filter client-side for tasks whose `name` starts with `"<ticket_prefix>-<n>:"`. If a named ticket isn't found, stop and ask — never guess which ticket.
   - Broad request ("pak alles op"): take every task currently in the `workflow.todo` section.
   - If nothing matches, tell the user there's nothing to pick up and stop.
2. **Move each to In progress.** For every ticket found, call `mcp__asana__update_tasks` (batched, one call for all of them) with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.in_progress>}]` per task. Re-adding a task to the project it's already in, with a different `section_id`, is how Asana moves it between board columns.
3. **Dispatch one subagent per ticket, in parallel.** In a single message, launch one `Agent` call per ticket — never one agent handling multiple tickets, and never tickets processed sequentially one Agent call at a time. Each subagent gets only its own ticket's context: title, the full "Doel / Verwacht eindproduct / Out of scope / Definition of Done / Kernpunten" notes, and its ticket ID.
4. **Each subagent's own job** (this is what you put in that subagent's prompt, not something you do here):
   - Read the ticket's "Verwacht eindproduct" and "Kernpunten" to judge scope: backend, frontend, or both.
   - Load `kozijnr-backend` and/or `kozijnr-frontend` accordingly (both, if the ticket spans the stack), plus whatever further skills/subagents those pull in (TDD, systematic-debugging, `shadcn`, etc.).
   - Treat the ticket's "Definition of Done" as the acceptance test for its own work, not a suggestion.
   - Report back: what was built, which files/branch/commit, and concrete steps to verify it.
5. **Per ticket, as its subagent finishes: log progress + handoff.** Call `mcp__asana__add_comment` on that specific ticket in het Nederlands, met dit vaste format zodat `asana-review` het als overdracht herkent:

   ```
   ▶ Overgedragen aan review

   Wat is gebouwd: <korte samenvatting>
   Bestanden/branch: <branch of commit, indien van toepassing>
   Hoe te verifiëren: <korte teststappen>
   ```
6. **Move that ticket to Ready for review.** Call `mcp__asana__update_tasks` with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.ready_for_review>}]`. Do this per ticket as soon as its subagent and comment are done — don't wait for the slowest ticket in the batch to move the fast ones.
7. **Rapporteer aan de gebruiker.** Meld in het Nederlands, per ticket, dat het in "Ready for review" staat, met de samenvatting van stap 5.

## Quick Reference

| Step | Section (from `config.workflow`) |
|---|---|
| Start work | `in_progress` |
| Finish work | `ready_for_review` |

Moving section = `update_tasks` → `add_projects: [{project_id, section_id}]`, not a separate "move" call.

One `Agent` call per ticket, all dispatched together — never combine tickets into one subagent, never dispatch them one at a time.

## Common Mistakes

- **Matching the wrong ticket** — `KOZ-1` is a text prefix of `KOZ-10`; always match on `"KOZ-N:"` including the colon, not a bare substring.
- **Doing the implementation inline** instead of dispatching a subagent — loses the isolation the user asked for and pollutes this session's context.
- **One subagent handling several tickets** — defeats the isolation; a bug or bad assumption in one ticket's work shouldn't be able to bleed into another's context.
- **Waiting for all tickets before moving any to Ready for review** — route each ticket the moment its own subagent and handoff comment are done.
- **Skipping the progress comment** — `asana-review` and the human need to know what changed and why without re-reading the whole diff from scratch.
